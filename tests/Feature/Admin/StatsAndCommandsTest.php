<?php

use App\Enums\AccessState;
use App\Filament\Widgets\AppointmentsChartWidget;
use App\Filament\Widgets\MessagesOverviewWidget;
use App\Filament\Widgets\MoneyOverviewWidget;
use App\Filament\Widgets\ShopsOverviewWidget;
use App\Filament\Widgets\SignupsChartWidget;
use App\Filament\Widgets\TopCitiesWidget;
use App\Filament\Widgets\TopShopsWidget;
use App\Filament\Widgets\UsageOverviewWidget;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\PlatformDailyStat;
use App\Models\Service;
use App\Models\User;
use App\Services\PlatformStatsService;
use App\Services\ShopAdminService;
use App\Support\CurrentShop;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

/*
| Platformstatistiek, widgets, planning en het admin-commando (docs/BOUWOPDRACHT.md §11). "Nu" is dinsdag 17 november 2026, 10:00.
*/

beforeEach(function () {
    $this->travelTo(at('2026-11-17 10:00'));
    Filament::setCurrentPanel('admin');
    app(CurrentShop::class)->disableScope();
    $this->stats = app(PlatformStatsService::class);
    $this->admin = User::factory()->create(['is_admin' => true]);
});

// ── Berekening ───────────────────────────────────────────────────────────────────────────────────────────

it('computes the platform numbers for a day', function () {
    billingOn();
    [$trial] = shopInState('trial');
    [$active] = shopInState('active');
    [$expired] = shopInState('expired');
    [$free] = shopInState('free');
    [$blocked] = shopInState('suspended');
    [$cancelled] = shopInState('expired');
    $unfinished = Barbershop::factory()->onboarding()->create();

    $sam = staffWithHours($active, 'Sam');
    staffWithHours($trial, 'Mo');
    staffWithHours($trial, 'Weg')->update(['is_active' => false]);
    $service = Service::factory()->create(['barbershop_id' => $active->id]);
    $book = fn (array $attributes) => Appointment::factory()->create([...['barbershop_id' => $active->id, 'staff_member_id' => $sam->id, 'service_id' => $service->id], ...$attributes]);

    // Vandaag (di 17 nov, Amsterdam)
    $book(['source' => 'online', 'created_at' => at('2026-11-17 08:00'), 'starts_at' => at('2026-11-17 09:00'), 'status' => 'completed', 'amount_paid_cents' => 3000]);
    $book(['source' => 'online', 'created_at' => at('2026-11-17 08:30'), 'starts_at' => at('2026-11-17 09:30'), 'status' => 'completed', 'amount_paid_cents' => 2000]);
    $book(['source' => 'staff', 'created_at' => at('2026-11-17 08:40'), 'starts_at' => at('2026-11-17 09:45'), 'status' => 'no_show']);
    // Gisteren: telt niet mee voor vandaag
    $book(['source' => 'online', 'created_at' => at('2026-11-16 08:00'), 'starts_at' => at('2026-11-16 09:00'), 'status' => 'completed', 'amount_paid_cents' => 999]);

    Customer::factory()->count(2)->create(['barbershop_id' => $active->id, 'user_id' => User::factory()]);
    Customer::factory()->count(3)->create(['barbershop_id' => $active->id]);

    // $active heeft al een actief abonnement van € 10 uit shopInState().
    subscriptionFor($expired, 'past_due', ['price_cents' => 1500, 'past_due_at' => now()->subDay()]);
    // Opgezegd en afgelopen: telt niet mee in MRR en de zaak is verlopen
    subscriptionFor($cancelled, 'cancelled', ['price_cents' => 2000, 'current_period_ends_at' => now()->subDay()]);
    $payment = new Payment;
    $payment->barbershop_id = $active->id;
    $payment->payable()->associate($active->subscriptions()->first());
    $payment->forceFill(['mollie_payment_id' => 'tr_1', 'amount_cents' => 1000, 'status' => 'paid', 'paid_at' => now()])->save();

    foreach ([['mail', 'sent', null], ['mail', 'sent', null], ['sms', 'sent', 8], ['sms', 'failed', 8], ['push', 'sent', null]] as [$channel, $status, $cost]) {
        MessageLog::query()->create(['barbershop_id' => $active->id, 'channel' => $channel, 'to' => 'x', 'type' => 'reminder_1', 'cost_cents' => $cost, 'status' => $status]);
    }

    $this->stats->compute(now());
    $value = fn (string $key) => PlatformDailyStat::query()->where('key', $key)->where('date', '2026-11-17')->value('value');

    expect($value('shops_total'))->toBe(Barbershop::count())
        // Proefperiode: de zaak uit shopInState en die in aanmelden (die heeft ook een proefperiode)
        ->and($value('shops_trial'))->toBe(2)->and($value('shops_free'))->toBe(1)->and($value('shops_active'))->toBe(1)
        ->and($value('shops_past_due'))->toBe(1)->and($value('shops_suspended'))->toBe(1)->and($value('shops_expired'))->toBe(1)
        ->and($value('shops_onboarded'))->toBe(Barbershop::count() - 1)
        ->and($value('signups'))->toBe(Barbershop::count())
        ->and($value('mrr_cents'))->toBe(2500)
        ->and($value('revenue_month_cents'))->toBe(1000)
        ->and($value('appointments_created'))->toBe(3)->and($value('appointments_online'))->toBe(2)->and($value('appointments_staff'))->toBe(1)
        ->and($value('appointments_done'))->toBe(2)->and($value('revenue_cents'))->toBe(5000)
        ->and($value('no_show_bp'))->toBe(2500)               // 1 no-show op (2 voltooid + 1 gisteren voltooid + 1 no-show) = 1/4
        ->and($value('active_barbers'))->toBe(2)
        ->and($value('customer_accounts'))->toBe(2)// 3 gasten die we zelf toevoegen + 4 die de afspraak-factory aanmaakt
        ->and($value('guest_customers'))->toBe(7)
        ->and($value('messages_mail'))->toBe(2)->and($value('messages_sms'))->toBe(2)->and($value('messages_push'))->toBe(1)->and($value('messages_whatsapp'))->toBe(0)
        ->and($value('messages_failed'))->toBe(1)->and($value('message_cost_cents'))->toBe(16);
});

it('recalculates the same day without duplicating rows', function () {
    shopWithOwner();

    $this->stats->compute(now());
    $first = PlatformDailyStat::query()->count();
    shopWithOwner();
    $this->stats->compute(now());

    expect(PlatformDailyStat::query()->count())->toBe($first)
        ->and($this->stats->latest('shops_total'))->toBe(2);
});

it('counts churn and conversion', function () {
    billingOn();
    [$a] = shopInState('active');
    [$b] = shopInState('active');
    [$c] = shopInState('expired');
    $c->forceFill(['trial_ends_at' => now()->subDays(20)])->save();
    $a->subscriptions()->update(['created_at' => '2026-09-01', 'mollie_subscription_id' => 'sub_a']);
    $b->subscriptions()->update(['created_at' => '2026-09-01', 'mollie_subscription_id' => 'sub_b']);

    // Eén van de twee opgezegd deze maand
    $b->subscriptions()->update(['status' => 'cancelled', 'cancelled_at' => '2026-11-10']);

    // Conversie: a en b hebben betaald; c niet. Alle drie hadden een proefperiode.
    foreach ([$a, $b] as $index => $shop) {
        $payment = new Payment;
        $payment->barbershop_id = $shop->id;
        $payment->payable()->associate($shop->subscriptions()->first());
        $payment->forceFill(['mollie_payment_id' => 'tr_'.$index, 'amount_cents' => 1000, 'status' => 'paid', 'paid_at' => '2026-10-05'])->save();
    }

    $a->forceFill(['trial_ends_at' => '2026-09-14'])->save();
    $b->forceFill(['trial_ends_at' => '2026-09-14'])->save();

    $this->stats->compute(now());

    expect($this->stats->latest('churn_bp'))->toBe(5000)           // 1 van 2
        ->and($this->stats->latest('conversion_bp'))->toBe(6667);  // 2 van 3
});

it('returns series with missing days as zero and sums a period', function () {
    $this->stats->compute(now());
    $this->stats->compute(now()->subDays(2));

    $series = $this->stats->series('shops_total', 4);

    expect(array_keys($series))->toBe(['2026-11-14', '2026-11-15', '2026-11-16', '2026-11-17'])
        ->and($series['2026-11-14'])->toBe(0)->and($series['2026-11-15'])->toBe(0);

    PlatformDailyStat::query()->where('key', 'signups')->update(['value' => 4]);

    expect($this->stats->sum('signups', CarbonImmutable::parse('2026-11-01'), now()))->toBe(8);
});

it('puts the day in the timezone of the platform', function () {
    // 16 november 23:30 UTC is al 17 november 00:30 in Amsterdam
    shopWithOwner(['created_at' => '2026-11-16 23:30:00']);
    shopWithOwner(['created_at' => '2026-11-16 22:30:00']);

    $this->stats->compute(now());

    expect(PlatformDailyStat::query()->where('key', 'signups')->where('date', '2026-11-17')->value('value'))->toBe(1);
});

it('never lets a shop scope hide data from the platform numbers', function () {
    app(CurrentShop::class)->enableScope();
    [$shop] = shopWithOwner();
    [$other] = shopWithOwner();
    app(CurrentShop::class)->set($shop);
    Appointment::factory()->create(['barbershop_id' => $other->id, 'status' => 'completed', 'amount_paid_cents' => 1000, 'starts_at' => at('2026-11-17 09:00'), 'created_at' => at('2026-11-17 08:00')]);
    Customer::factory()->create(['barbershop_id' => $other->id]);

    $this->stats->compute(now());

    // De afspraak maakte zelf ook een klant aan, plus de klant die we erbij zetten.
    expect($this->stats->latest('appointments_done'))->toBe(1)->and($this->stats->latest('guest_customers'))->toBe(2);
});

it('computes on first use so the dashboard is never empty', function () {
    shopWithOwner();

    expect($this->stats->hasData())->toBeFalse();

    $this->stats->ensureToday();

    expect($this->stats->hasData())->toBeTrue()->and($this->stats->latest('shops_total'))->toBe(1);
});

// ── Commando en planning ─────────────────────────────────────────────────────────────────────────────────

it('fills in history with stats:compute and is scheduled every night', function () {
    shopWithOwner();

    $this->artisan('stats:compute --days=3')->expectsOutputToContain('3 dag(en)')->assertSuccessful();

    expect(PlatformDailyStat::query()->distinct()->pluck('date')->map(fn ($date) => $date->toDateString())->sort()->values()->all())
        ->toBe(['2026-11-15', '2026-11-16', '2026-11-17']);

    Artisan::call('schedule:list');
    expect(Artisan::output())->toContain('stats:compute --days=2');
});

it('makes an admin from an existing user', function () {
    $user = User::factory()->create(['email' => 'jan@example.com']);

    $this->artisan('barbercuts:make-admin Jan@Example.com')->expectsOutputToContain('is nu platformadmin')->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();
});

it('makes an admin from a new account with a strong password', function () {
    $this->artisan('barbercuts:make-admin nieuw@example.com --name=Eigenaar')
        ->expectsQuestion('Kies een wachtwoord voor het nieuwe account (minimaal 12 tekens)', 'een-lang-wachtwoord-1')
        ->assertSuccessful();

    $user = User::where('email', 'nieuw@example.com')->firstOrFail();

    expect($user->is_admin)->toBeTrue()->and($user->name)->toBe('Eigenaar')->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('een-lang-wachtwoord-1', $user->password))->toBeTrue();
});

it('refuses a bad e-mail address or a short password', function () {
    $this->artisan('barbercuts:make-admin geen-email')->assertFailed();
    $this->artisan('barbercuts:make-admin nieuw@example.com')->expectsQuestion('Kies een wachtwoord voor het nieuwe account (minimaal 12 tekens)', 'kort')->assertFailed();

    expect(User::where('email', 'nieuw@example.com')->exists())->toBeFalse();
});

// ── Widgets ──────────────────────────────────────────────────────────────────────────────────────────────

it('renders every dashboard widget with real numbers', function () {
    billingOn();
    [$active] = shopInState('active');
    [$expired] = shopInState('expired');
    $active->forceFill(['city' => 'Utrecht'])->save();
    $expired->forceFill(['city' => 'Utrecht'])->save();
    Appointment::factory()->count(2)->create(['barbershop_id' => $active->id]);

    Livewire::actingAs($this->admin)->test(ShopsOverviewWidget::class)->assertSee('Zaken')->assertSee('Betaald')->assertSee('Verlopen')->assertSee('Geblokkeerd');
    Livewire::actingAs($this->admin)->test(MoneyOverviewWidget::class)->assertSee('MRR')->assertSee('€ 10,00')->assertSee('Churn deze maand');
    Livewire::actingAs($this->admin)->test(UsageOverviewWidget::class)->assertSee('Afspraken deze maand')->assertSee('Online aandeel');
    Livewire::actingAs($this->admin)->test(MessagesOverviewWidget::class)->assertSee('E-mail')->assertSee('WhatsApp')->assertSee('Mislukt');
    Livewire::actingAs($this->admin)->test(SignupsChartWidget::class)->assertSuccessful();
    Livewire::actingAs($this->admin)->test(AppointmentsChartWidget::class)->assertSuccessful();
    Livewire::actingAs($this->admin)->test(TopShopsWidget::class)->assertCanSeeTableRecords([$active]);
    Livewire::actingAs($this->admin)->test(TopCitiesWidget::class)->assertSee('Utrecht')->assertSee('2');
});

it('shows the billing state in the money widget', function () {
    Livewire::actingAs($this->admin)->test(MoneyOverviewWidget::class)->assertSee('Facturering staat uit');
});

// ── Beheerdiensten ───────────────────────────────────────────────────────────────────────────────────────

it('gives a free period that beats everything, and logs who did it', function () {
    billingOn();
    [$shop] = shopInState('expired');
    $this->actingAs($this->admin);

    app(ShopAdminService::class)->giveFreePeriod($shop, now()->addMonths(6), 'Pilot');

    $log = ActivityLog::query()->firstOrFail();

    expect($shop->fresh()->accessState())->toBe(AccessState::Free)
        ->and($log->user_id)->toBe($this->admin->id)->and($log->action)->toBe('shop.free_period');

    $this->travelTo(now()->addMonths(7));
    expect($shop->fresh()->accessState())->toBe(AccessState::Expired);
});
