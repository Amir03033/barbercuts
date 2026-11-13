<?php

use App\Enums\AccessState;
use App\Exceptions\ShopReadOnlyException;
use App\Filament\Pages\PlatformSettingsPage;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Barbershops\BarbershopResource;
use App\Filament\Resources\Barbershops\Pages\ListBarbershops;
use App\Filament\Resources\Barbershops\Pages\ViewBarbershop;
use App\Filament\Resources\MessageLogs\MessageLogResource;
use App\Filament\Resources\MessageLogs\Pages\ListMessageLogs;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Service;
use App\Models\User;
use App\Notifications\SubscriptionPriceChanged;
use App\Rules\AvailableSlug;
use App\Services\Billing\BillingGateway;
use App\Support\CurrentShop;
use App\Support\Features;
use App\Support\PlatformSettings;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Fakes\FakeBillingGateway;

/*
| Adminpanel op /admin (docs/BOUWOPDRACHT.md §11 en §12). "Nu" is dinsdag 17 november 2026, 10:00.
*/

beforeEach(function () {
    $this->travelTo(at('2026-11-17 10:00'));
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'Beheerder']);

    // In het panel staat de tenant-scope uit (DisableShopScope); dat bootsen Livewire-componenttests niet na.
    app(CurrentShop::class)->disableScope();
});

// ── Toegang ──────────────────────────────────────────────────────────────────────────────────────────────

it('sends guests to the login page and refuses non-admins with a 403', function () {
    app(CurrentShop::class)->enableScope();

    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/barbershops')->assertRedirect('/admin/login');

    [, $owner] = shopWithOwner();
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    $this->actingAs($owner)->get('/admin')->assertForbidden();
    $this->actingAs($owner)->get('/admin/barbershops')->assertForbidden();
});

it('lets an admin in, and demands two-factor authentication', function () {
    app(CurrentShop::class)->enableScope();

    $response = $this->actingAs($this->admin)->get('/admin');

    // Zonder ingestelde 2FA stuurt Filament de admin eerst naar het instellen ervan.
    expect($response->status())->not->toBe(403)->and($response->status())->toBeIn([200, 302]);

    if ($response->status() === 302) {
        expect($response->headers->get('Location'))->toContain('multi-factor-authentication');
    }

    expect(Filament::getPanel('admin')->isMultiFactorAuthenticationRequired())->toBeTrue();
});

it('turns the tenant scope off in the panel and never in the app', function () {
    app(CurrentShop::class)->enableScope();
    [$shop, $owner] = shopWithOwner();

    $this->actingAs($owner)->get('/app');
    expect(app(CurrentShop::class)->scopeIsDisabled())->toBeFalse();

    app(CurrentShop::class)->clear();
    $this->actingAs($this->admin)->get('/admin/barbershops');
    expect(app(CurrentShop::class)->scopeIsDisabled())->toBeTrue();
});

it('only makes users with the admin flag able to access the panel', function () {
    $panel = Filament::getPanel('admin');

    expect($this->admin->canAccessPanel($panel))->toBeTrue()
        ->and(User::factory()->create()->canAccessPanel($panel))->toBeFalse();
});

it('has no create or edit screens for read-only data', function () {
    foreach ([UserResource::class, SubscriptionResource::class, PaymentResource::class,
        AppointmentResource::class, MessageLogResource::class,
        ActivityLogResource::class, BarbershopResource::class] as $resource) {
        expect($resource::canCreate())->toBeFalse()
            ->and(array_keys($resource::getPages()))->not->toContain('create')->not->toContain('edit');
    }
});

// ── Zaken ────────────────────────────────────────────────────────────────────────────────────────────────

it('lists the shops with their status and filters on status and city', function () {
    billingOn();
    [$expired] = shopInState('expired');
    [$trial] = shopInState('trial');
    [$suspended] = shopInState('suspended');
    $expired->forceFill(['city' => 'Utrecht'])->save();
    $trial->forceFill(['city' => 'Zwolle'])->save();

    Livewire::actingAs($this->admin)->test(ListBarbershops::class)
        ->assertCanSeeTableRecords([$expired, $trial, $suspended])
        ->assertSee('Verlopen')->assertSee('Proefperiode')->assertSee('Geblokkeerd')
        ->filterTable('state', 'expired')
        ->assertCanSeeTableRecords([$expired])->assertCanNotSeeTableRecords([$trial, $suspended])
        ->filterTable('state', 'suspended')
        ->assertCanSeeTableRecords([$suspended])->assertCanNotSeeTableRecords([$expired, $trial])
        ->removeTableFilter('state')
        ->filterTable('city', 'Zwolle')
        ->assertCanSeeTableRecords([$trial])->assertCanNotSeeTableRecords([$expired, $suspended]);
});

it('searches shops by name', function () {
    [$a] = shopWithOwner(['name' => 'Fade Factory']);
    [$b] = shopWithOwner(['name' => 'Knip Kapper']);

    Livewire::actingAs($this->admin)->test(ListBarbershops::class)
        ->searchTable('Fade')->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
});

it('shows a shop with its details, usage and revenue', function () {
    [$shop] = shopWithOwner(['name' => 'Fade Factory', 'city' => 'Utrecht']);
    $staff = staffWithHours($shop, 'Sam');
    $service = Service::factory()->create(['barbershop_id' => $shop->id]);
    Appointment::factory()->count(2)->create(['barbershop_id' => $shop->id, 'staff_member_id' => $staff->id, 'service_id' => $service->id,
        'status' => 'completed', 'amount_paid_cents' => 2500]);

    Livewire::actingAs($this->admin)->test(ViewBarbershop::class, ['record' => $shop->getRouteKey()])
        ->assertSee('Fade Factory')->assertSee('Utrecht')->assertSee('Sam')->assertSee('€ 50,00')->assertSee('Gratis');
});

it('gives a shop a free period in three taps and logs it', function (array $data, string $until) {
    billingOn();
    [$shop] = shopInState('expired');

    Livewire::actingAs($this->admin)->test(ListBarbershops::class)
        ->callTableAction('freePeriod', $shop, data: $data)
        ->assertHasNoTableActionErrors()
        ->assertNotified();

    $fresh = $shop->fresh();
    $log = ActivityLog::query()->where('action', 'shop.free_period')->firstOrFail();

    expect($fresh->free_until->toDateString())->toBe($until)
        ->and($fresh->free_reason)->toBe('Kennismaking')
        ->and($fresh->accessState())->toBe(AccessState::Free)
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->barbershop_id)->toBe($shop->id)
        ->and($log->properties)->toMatchArray(['until' => $until, 'reason' => 'Kennismaking']);
})->with([
    '3 months' => [['duration' => '3', 'reason' => 'Kennismaking'], '2027-02-17'],
    '12 months' => [['duration' => '12', 'reason' => 'Kennismaking'], '2027-11-17'],
    'until a date' => [['duration' => 'custom', 'until' => '2026-12-31', 'reason' => 'Kennismaking'], '2026-12-31'],
]);

it('requires a date for a custom free period', function () {
    [$shop] = shopWithOwner();

    Livewire::actingAs($this->admin)->test(ListBarbershops::class)
        ->callTableAction('freePeriod', $shop, data: ['duration' => 'custom', 'until' => null])
        ->assertHasTableActionErrors(['until' => 'required']);

    expect($shop->fresh()->free_until)->toBeNull();
});

it('extends a trial from its end, or from now when it is over', function () {
    billingOn();
    [$running] = shopInState('trial');         // proef tot 27 november
    [$over] = shopInState('expired');

    Livewire::actingAs($this->admin)->test(ListBarbershops::class)
        ->callTableAction('extendTrial', $running, data: ['days' => 14])
        ->callTableAction('extendTrial', $over, data: ['days' => 14]);

    expect($running->fresh()->trial_ends_at->toDateString())->toBe('2026-12-11')
        ->and($over->fresh()->trial_ends_at->toDateString())->toBe('2026-12-01')
        ->and($over->fresh()->accessState())->toBe(AccessState::Trial)
        ->and(ActivityLog::query()->where('action', 'shop.trial_extended')->count())->toBe(2);
});

it('blocks and unblocks a shop with a reason, without deleting anything', function () {
    billingOn();
    [$shop] = shopInState('active');
    $service = Service::factory()->create(['barbershop_id' => $shop->id]);

    Livewire::actingAs($this->admin)->test(ListBarbershops::class)
        ->assertTableActionVisible('suspend', $shop)->assertTableActionHidden('unsuspend', $shop)
        ->callTableAction('suspend', $shop, data: ['reason' => 'Misbruik']);

    expect($shop->fresh()->accessState())->toBe(AccessState::Suspended)
        ->and($shop->fresh()->suspended_reason)->toBe('Misbruik')
        ->and(Service::withoutGlobalScopes()->whereKey($service->id)->exists())->toBeTrue();

    Livewire::actingAs($this->admin)->test(ListBarbershops::class)
        ->assertTableActionHidden('suspend', $shop->fresh())->assertTableActionVisible('unsuspend', $shop->fresh())
        ->callTableAction('unsuspend', $shop->fresh());

    expect($shop->fresh()->accessState())->toBe(AccessState::Active)->and($shop->fresh()->suspended_reason)->toBeNull()
        ->and(ActivityLog::query()->orderBy('id')->pluck('action')->all())->toBe(['shop.suspended', 'shop.unsuspended']);
});

it('requires a reason to block a shop', function () {
    [$shop] = shopWithOwner();

    Livewire::actingAs($this->admin)->test(ListBarbershops::class)
        ->callTableAction('suspend', $shop, data: ['reason' => ''])
        ->assertHasTableActionErrors(['reason' => 'required']);

    expect($shop->fresh()->suspended_at)->toBeNull();
});

it('only offers to log in as the owner when the shop has one', function () {
    [$shop] = shopWithOwner();
    $orphan = Barbershop::factory()->create();

    Livewire::actingAs($this->admin)->test(ListBarbershops::class)
        ->assertTableActionVisible('impersonate', $shop)->assertTableActionHidden('impersonate', $orphan);
});

// ── Inloggen als eigenaar ────────────────────────────────────────────────────────────────────────────────

it('logs in as the owner through a signed link, with a banner and a way back, and logs both', function () {
    app(CurrentShop::class)->enableScope();
    [$shop, $owner] = shopWithOwner(['name' => 'Fade Factory']);
    $url = URL::temporarySignedRoute('impersonate.start', now()->addMinute(), ['shop' => $shop->id]);

    $this->actingAs($this->admin)->get($url)->assertRedirect('/app');

    $this->assertAuthenticatedAs($owner);
    expect(session('impersonator_id'))->toBe($this->admin->id);

    $this->get('/app')->assertOk()->assertSee('Je bekijkt als')->assertSee($owner->name)->assertSee('Fade Factory')->assertSee('Terug naar admin');

    $this->post('/impersonate/stop')->assertRedirect('/admin');

    $this->assertAuthenticatedAs($this->admin);
    expect(session()->has('impersonator_id'))->toBeFalse();
    $this->get('/app')->assertDontSee('Je bekijkt als');

    expect(ActivityLog::query()->orderBy('id')->pluck('action')->all())->toBe(['impersonate.start', 'impersonate.stop'])
        ->and(ActivityLog::query()->first()->user_id)->toBe($this->admin->id)
        ->and(ActivityLog::query()->first()->barbershop_id)->toBe($shop->id);
});

it('refuses impersonation without a valid signature or for anyone but an admin', function () {
    [$shop, $owner] = shopWithOwner();
    $signed = URL::temporarySignedRoute('impersonate.start', now()->addMinute(), ['shop' => $shop->id]);

    // Niet gesigneerd, verlopen of van een ander id
    $this->actingAs($this->admin)->get("/impersonate/{$shop->id}")->assertForbidden();
    $this->actingAs($this->admin)->get(URL::temporarySignedRoute('impersonate.start', now()->subMinute(), ['shop' => $shop->id]))->assertForbidden();

    // Niet-admin met een geldige link
    auth()->logout();
    $this->actingAs($owner)->get($signed)->assertForbidden();
    $this->actingAs(User::factory()->create())->get($signed)->assertForbidden();

    // Niet ingelogd
    auth()->logout();
    $this->get($signed)->assertRedirect('/inloggen');

    expect(ActivityLog::query()->count())->toBe(0);
});

it('cannot stop impersonating without having started, nor as a non-admin', function () {
    $this->actingAs(User::factory()->create())->post('/impersonate/stop')->assertForbidden();
    $this->actingAs($this->admin)->post('/impersonate/stop')->assertForbidden();
});

it('does not let an admin impersonate while already impersonating, nor a shop without an owner', function () {
    [$shop] = shopWithOwner();
    $orphan = Barbershop::factory()->create();

    $this->actingAs($this->admin)->get(URL::temporarySignedRoute('impersonate.start', now()->addMinute(), ['shop' => $orphan->id]))->assertNotFound();

    $this->get(URL::temporarySignedRoute('impersonate.start', now()->addMinute(), ['shop' => $shop->id]))->assertRedirect('/app');
    $this->get(URL::temporarySignedRoute('impersonate.start', now()->addMinute(), ['shop' => $shop->id]))->assertForbidden();
});

it('keeps an impersonating admin read-only at an expired shop', function () {
    app(CurrentShop::class)->enableScope();
    billingOn();
    [$shop] = shopInState('expired');
    $service = Service::factory()->create(['barbershop_id' => $shop->id]);

    $this->actingAs($this->admin)->get(URL::temporarySignedRoute('impersonate.start', now()->addMinute(), ['shop' => $shop->id]))->assertRedirect('/app');
    $this->get('/app')->assertOk()->assertSee('Alleen lezen');

    expect(fn () => $service->update(['name' => 'Aangepast']))->toThrow(ShopReadOnlyException::class);
});

// ── Gebruikers ───────────────────────────────────────────────────────────────────────────────────────────

it('lists users, searches them and filters admins', function () {
    $jan = User::factory()->create(['name' => 'Jan Jansen', 'email' => 'jan@example.com']);
    $piet = User::factory()->create(['name' => 'Piet Pieters']);

    Livewire::actingAs($this->admin)->test(ListUsers::class)
        ->assertCanSeeTableRecords([$jan, $piet, $this->admin])
        ->searchTable('jan@example')->assertCanSeeTableRecords([$jan])->assertCanNotSeeTableRecords([$piet])
        ->searchTable('')->filterTable('is_admin', true)->assertCanSeeTableRecords([$this->admin])->assertCanNotSeeTableRecords([$jan, $piet]);
});

it('grants and revokes admin rights, but never lets an admin lock himself out', function () {
    $jan = User::factory()->create();

    $page = Livewire::actingAs($this->admin)->test(ListUsers::class)
        ->callTableAction('toggleAdmin', $jan);
    expect($jan->fresh()->is_admin)->toBeTrue();

    $page->callTableAction('toggleAdmin', $jan->fresh());
    expect($jan->fresh()->is_admin)->toBeFalse();

    $page->callTableAction('toggleAdmin', $this->admin);
    expect($this->admin->fresh()->is_admin)->toBeTrue()
        ->and(ActivityLog::query()->pluck('action')->all())->toBe(['user.admin_granted', 'user.admin_revoked']);
});

it('verifies an e-mail address and sends a password reset link', function () {
    Notification::fake();
    $jan = User::factory()->unverified()->create();

    Livewire::actingAs($this->admin)->test(ListUsers::class)
        ->assertTableActionVisible('verifyEmail', $jan)
        ->callTableAction('verifyEmail', $jan)
        ->assertTableActionHidden('verifyEmail', $jan->fresh())
        ->callTableAction('resetPassword', $jan);

    expect($jan->fresh()->hasVerifiedEmail())->toBeTrue();
    Notification::assertSentTo($jan, ResetPassword::class);
});

// ── Plannen ──────────────────────────────────────────────────────────────────────────────────────────────

it('creates a plan with the price in euros stored as cents', function () {
    Livewire::actingAs($this->admin)->test(CreatePlan::class)
        ->fillForm(['name' => 'Pro', 'price_cents' => 12.5, 'trial_days' => 7, 'is_active' => true])
        ->call('create')->assertHasNoFormErrors();

    expect(Plan::query()->where('name', 'Pro')->firstOrFail()->only(['price_cents', 'trial_days']))->toBe(['price_cents' => 1250, 'trial_days' => 7]);
});

it('changes the plan price for new subscriptions only, or for existing ones too, after asking', function () {
    Notification::fake();
    $gateway = new FakeBillingGateway;
    app()->instance(BillingGateway::class, $gateway);
    [$shop, $owner] = shopInState('expired');
    $plan = Plan::current();
    $subscription = subscriptionFor($shop, 'active', ['plan_id' => $plan->id, 'mollie_subscription_id' => 'sub_x']);
    $shop->forceFill(['mollie_customer_id' => 'cst_1'])->save();

    $page = Livewire::actingAs($this->admin)->test(ListPlans::class)
        ->assertSee('€ 10,00 per maand')
        ->callTableAction('changePrice', $plan, data: ['price' => 12, 'scope' => 'new'])->assertHasNoTableActionErrors();

    expect($plan->fresh()->price_cents)->toBe(1200)->and($subscription->fresh()->price_cents)->toBe(1000)->and($gateway->updated)->toBe([]);

    $page->callTableAction('changePrice', $plan->fresh(), data: ['price' => 15, 'scope' => 'all']);

    expect($plan->fresh()->price_cents)->toBe(1500)->and($subscription->fresh()->price_cents)->toBe(1500)
        ->and($gateway->updated)->toHaveCount(1)
        ->and(ActivityLog::query()->where('action', 'plan.price_changed')->count())->toBe(2);
    Notification::assertSentTo($owner, SubscriptionPriceChanged::class);
});

it('rejects a zero or negative price', function (mixed $price) {
    $plan = Plan::current();

    Livewire::actingAs($this->admin)->test(ListPlans::class)
        ->callTableAction('changePrice', $plan, data: ['price' => $price, 'scope' => 'new'])
        ->assertHasTableActionErrors(['price']);

    expect($plan->fresh()->price_cents)->toBe(1000);
})->with([0, -5, 'gratis']);

// ── Alleen-lezen lijsten over alle zaken ─────────────────────────────────────────────────────────────────

it('shows subscriptions, payments, appointments, messages and the log across all shops', function () {
    [$shopA] = shopWithOwner(['name' => 'Zaak A']);
    [$shopB] = shopWithOwner(['name' => 'Zaak B']);
    $subscription = subscriptionFor($shopA, 'active', ['mollie_subscription_id' => 'sub_x']);

    $payment = new Payment;
    $payment->barbershop_id = $shopA->id;
    $payment->payable()->associate($subscription);
    $payment->forceFill(['mollie_payment_id' => 'tr_abc', 'amount_cents' => 1000, 'status' => 'paid'])->save();

    $appointments = collect([$shopA, $shopB])->map(fn (Barbershop $shop) => Appointment::factory()->create(['barbershop_id' => $shop->id]));
    $message = MessageLog::query()->create(['barbershop_id' => $shopA->id, 'channel' => 'sms', 'to' => '+31612345678', 'type' => 'reminder_1', 'cost_cents' => 8, 'status' => 'sent']);
    ActivityLog::record('test.action', $shopB);

    Livewire::actingAs($this->admin)->test(ListSubscriptions::class)->assertCanSeeTableRecords([$subscription])->assertSee('Zaak A');
    Livewire::actingAs($this->admin)->test(ListPayments::class)->assertCanSeeTableRecords([$payment])->assertSee('tr_abc');
    Livewire::actingAs($this->admin)->test(ListAppointments::class)->assertCanSeeTableRecords($appointments)
        ->filterTable('barbershop_id', $shopA->id)->assertCanSeeTableRecords([$appointments[0]])->assertCanNotSeeTableRecords([$appointments[1]]);
    Livewire::actingAs($this->admin)->test(ListMessageLogs::class)->assertCanSeeTableRecords([$message])
        ->filterTable('channel', 'mail')->assertCanNotSeeTableRecords([$message]);
    Livewire::actingAs($this->admin)->test(ListActivityLogs::class)->assertSee('test.action')->assertSee('Zaak B');
});

// ── Instellingen ─────────────────────────────────────────────────────────────────────────────────────────

it('lets the admin override feature flags and reserve extra slugs', function () {
    expect(Features::deposits())->toBeFalse()->and(Features::sms())->toBeFalse();

    Livewire::actingAs($this->admin)->test(PlatformSettingsPage::class)
        ->set('deposits', true)->set('sms', true)->set('extraSlugs', "Kapper\nsalon, over-ons")
        ->call('save')->assertNotified('Instellingen opgeslagen');

    PlatformSettings_forget();

    expect(Features::deposits())->toBeTrue()->and(Features::sms())->toBeTrue()->and(Features::whatsapp())->toBeFalse()
        ->and(Features::reservedSlugs())->toContain('admin')->toContain('kapper')->toContain('salon')->toContain('over-ons');

    // Een gereserveerde slug kan niet meer gekozen worden
    $rule = new AvailableSlug;
    $failures = [];
    $rule->validate('slug', 'salon', function (string $message) use (&$failures) {
        $failures[] = $message;
    });

    expect($failures)->toHaveCount(1);
});

it('rejects an invalid reserved slug', function () {
    Livewire::actingAs($this->admin)->test(PlatformSettingsPage::class)
        ->set('extraSlugs', 'goed slecht!')->call('save')->assertNotified('"slecht!" is geen geldige slug');

    expect(PlatformSettings::has('reserved_slugs_extra'))->toBeFalse();
});

it('turns billing on and gives shops without a trial a trial, then turns it off again', function () {
    [$noTrial] = shopWithOwner(['trial_ends_at' => null]);
    [$withTrial] = shopWithOwner(['trial_ends_at' => now()->addDays(3)]);
    [$gift] = shopWithOwner(['trial_ends_at' => null, 'free_until' => now()->addMonths(2)]);
    [$paying] = shopWithOwner(['trial_ends_at' => null]);
    subscriptionFor($paying, 'active');
    [$blocked] = shopWithOwner(['trial_ends_at' => null, 'suspended_at' => now()]);

    expect(Features::billing())->toBeFalse();

    $page = Livewire::actingAs($this->admin)->test(PlatformSettingsPage::class)
        ->assertSee('Betalen via Mollie aanzetten')->assertDontSee('Betalen via Mollie uitzetten')
        ->callAction('enableBilling')->assertNotified('Facturering staat aan');

    expect(Features::billing())->toBeTrue()
        ->and($noTrial->fresh()->trial_ends_at->toDateString())->toBe('2026-12-01')       // 14 dagen uit het plan
        ->and($noTrial->fresh()->accessState())->toBe(AccessState::Trial)
        ->and($withTrial->fresh()->trial_ends_at->toDateString())->toBe('2026-11-20')     // onaangeroerd
        ->and($gift->fresh()->trial_ends_at)->toBeNull()->and($gift->fresh()->accessState())->toBe(AccessState::Free)
        ->and($paying->fresh()->trial_ends_at)->toBeNull()->and($paying->fresh()->accessState())->toBe(AccessState::Active)
        ->and($blocked->fresh()->trial_ends_at)->toBeNull();

    $page->assertSee('Betalen via Mollie uitzetten')->callAction('disableBilling')->assertNotified('Facturering staat uit');

    expect(Features::billing())->toBeFalse()->and($noTrial->fresh()->accessState())->toBe(AccessState::Free)
        ->and(ActivityLog::query()->pluck('action')->all())->toBe(['billing.enabled', 'billing.disabled']);
});

function PlatformSettings_forget(): void
{
    PlatformSettings::forget();
}
