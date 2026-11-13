<?php

use App\Enums\AccessState;
use App\Exceptions\BookingException;
use App\Exceptions\ShopReadOnlyException;
use App\Livewire\App\Services\Form as ServiceForm;
use App\Livewire\App\Settings;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Plan;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\BookingData;
use App\Services\Booking\CustomerInput;
use App\Services\ShopSetupService;
use App\Support\CurrentShop;
use Livewire\Livewire;

/*
| Toegangsstatus (docs/BOUWOPDRACHT.md §7.7 en §12). "Nu" is dinsdag 17 november 2026, 10:00 in Amsterdam.
*/

beforeEach(function () {
    $this->travelTo(at('2026-11-17 10:00'));
    billingOn();
});

it('calculates every access state', function (string $state, AccessState $expected) {
    [$shop] = shopInState($state);

    expect($shop->accessState())->toBe($expected);
})->with([
    ['trial', AccessState::Trial],
    ['free', AccessState::Free],
    ['active', AccessState::Active],
    ['past_due', AccessState::PastDue],
    ['expired', AccessState::Expired],
    ['suspended', AccessState::Suspended],
]);

it('lets a free period of the admin beat everything except a suspension', function () {
    [$expired] = shopInState('expired');
    $expired->forceFill(['free_until' => now()->addMonth()])->save();

    [$trial] = shopInState('trial');
    $trial->forceFill(['trial_ends_at' => now()->subDay(), 'free_until' => now()->addMonth()])->save();

    [$suspended] = shopInState('suspended');
    $suspended->forceFill(['free_until' => now()->addMonth()])->save();

    expect($expired->accessState())->toBe(AccessState::Free)
        ->and($trial->fresh()->accessState())->toBe(AccessState::Free)
        ->and($suspended->fresh()->accessState())->toBe(AccessState::Suspended);
});

it('treats a free period that is over as if it was never given', function () {
    [$shop] = shopInState('expired');
    $shop->forceFill(['free_until' => now()->subMinute()])->save();

    expect($shop->accessState())->toBe(AccessState::Expired);
});

it('keeps a cancelled subscription active until the end of the paid period', function () {
    [$shop] = shopInState('expired');
    $subscription = subscriptionFor($shop, 'cancelled', ['cancelled_at' => now(), 'current_period_ends_at' => now()->addDays(10)]);

    expect($shop->accessState())->toBe(AccessState::Active);

    $this->travelTo(now()->addDays(11));
    expect($shop->fresh()->accessState())->toBe(AccessState::Expired);
});

it('gives a failed payment 7 days of full access with a banner, then the shop expires', function () {
    [$shop, $owner] = shopInState('past_due');

    expect($shop->accessState())->toBe(AccessState::PastDue)->and($shop->accessState()->isWritable())->toBeTrue();
    $this->actingAs($owner)->get('/app')->assertOk()->assertSee('Betaling mislukt');

    // De betaling mislukte 2 dagen geleden: na 4 dagen erbij is het 6 dagen, na nog 3 dagen 9 dagen.
    $this->travelTo(now()->addDays(4));
    expect($shop->fresh()->accessState())->toBe(AccessState::PastDue);

    $this->travelTo(now()->addDays(3));
    expect($shop->fresh()->accessState())->toBe(AccessState::Expired);
});

it('makes every shop free when billing is off, except a suspended one', function () {
    config(['barbercuts.billing.enabled' => false]);

    foreach (['trial', 'expired', 'active', 'past_due'] as $state) {
        expect(shopInState($state)[0]->accessState())->toBe(AccessState::Free);
    }

    expect(shopInState('suspended')[0]->accessState())->toBe(AccessState::Suspended);
});

it('has an sql scope that agrees with accessState for every state', function (bool $billing) {
    config(['barbercuts.billing.enabled' => $billing]);

    $shops = collect(['trial', 'free', 'active', 'past_due', 'expired', 'suspended'])
        ->flatMap(fn (string $state) => [shopInState($state)[0], shopInState($state)[0]]);

    // Randgevallen: opgezegd en nog betaald, opgezegd en verlopen, proef voorbij met mislukte betaling
    [$cancelledRunning] = shopInState('expired');
    subscriptionFor($cancelledRunning, 'cancelled', ['current_period_ends_at' => now()->addDays(3)]);
    [$cancelledOver] = shopInState('expired');
    subscriptionFor($cancelledOver, 'cancelled', ['current_period_ends_at' => now()->subDays(3)]);
    [$pendingOnly] = shopInState('trial');
    subscriptionFor($pendingOnly, 'pending');
    [$oldPastDue] = shopInState('expired');
    subscriptionFor($oldPastDue, 'past_due', ['past_due_at' => now()->subDays(9)]);

    $shops = Barbershop::query()->get();

    foreach (AccessState::cases() as $state) {
        $expected = $shops->filter(fn (Barbershop $shop) => $shop->accessState() === $state)->pluck('id')->sort()->values()->all();
        $actual = Barbershop::query()->inAccessState($state)->pluck('id')->sort()->values()->all();

        expect($actual)->toBe($expected, $state->value);
    }
})->with([true, false]);

// ── Alleen-lezen ─────────────────────────────────────────────────────────────────────────────────────────

it('lets an expired shop read everything but change nothing', function () {
    [$shop, $owner] = shopInState('expired');
    $service = Service::factory()->create(['barbershop_id' => $shop->id, 'name' => 'Knippen']);
    StaffMember::factory()->create(['barbershop_id' => $shop->id]);
    actingInShop($owner, $shop);

    // Lezen kan
    $this->get('/app')->assertOk()->assertSee('Alleen lezen');
    $this->get('/app/diensten')->assertOk()->assertSee('Knippen');

    // Wijzigen via een model, een Livewire-actie of een nieuw record kan niet
    expect(fn () => $service->update(['name' => 'Anders']))->toThrow(ShopReadOnlyException::class)
        ->and(fn () => Service::factory()->create(['barbershop_id' => $shop->id]))->toThrow(ShopReadOnlyException::class)
        ->and(fn () => $service->delete())->toThrow(ShopReadOnlyException::class)
        ->and(fn () => $shop->update(['accent_color' => '#ff0000']))->toThrow(ShopReadOnlyException::class);

    Livewire::test(Settings::class)->set('slotInterval', '30')->call('save')->assertForbidden();
    Livewire::test(ServiceForm::class)->set('name', 'Nieuw')->set('duration', '30')->set('price', '10')->call('save')->assertForbidden();

    expect($service->fresh()->name)->toBe('Knippen');
});

it('tells the browser why a change was refused', function () {
    [$shop, $owner] = shopInState('expired');
    actingInShop($owner, $shop);

    $response = $this->actingAs($owner)->withSession(['current_shop_id' => $shop->id])->post('/livewire/update', ['components' => []]);
    $exception = new ShopReadOnlyException;

    expect($exception->getStatusCode())->toBe(403)->and($exception->getHeaders())->toBe(['X-Shop-Read-Only' => '1']);
});

it('lets the platform admin and the console change an expired shop', function () {
    [$shop] = shopInState('expired');
    $service = Service::factory()->create(['barbershop_id' => $shop->id]);
    app(CurrentShop::class)->set($shop);

    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $service->update(['name' => 'Door admin']);

    auth()->logout();
    $service->update(['name' => 'Door de queue']);

    expect($service->fresh()->name)->toBe('Door de queue');
});

it('lets the owner work in every writable state', function (string $state) {
    [$shop, $owner] = shopInState($state);
    $service = Service::factory()->create(['barbershop_id' => $shop->id]);
    actingInShop($owner, $shop);

    $service->update(['name' => 'Nieuwe naam']);

    expect($service->fresh()->name)->toBe('Nieuwe naam');
})->with(['trial', 'free', 'active', 'past_due']);

it('does not block a customer who cancels at an expired shop', function () {
    [$shop] = shopInState('trial');
    $staff = staffWithHours($shop);
    $service = Service::factory()->create(['barbershop_id' => $shop->id]);
    $appointment = app(AppointmentService::class)->book(new BookingData($shop, $service, $staff, at('2026-11-20 10:00'), new CustomerInput('Daan', '0612345678')), null);

    $shop->forceFill(['trial_ends_at' => now()->subDay()])->save();
    app(CurrentShop::class)->set($shop->fresh());

    app(AppointmentService::class)->cancel($appointment, null);

    expect($appointment->fresh()->status->value)->toBe('cancelled');
});

// ── Boekingspagina ───────────────────────────────────────────────────────────────────────────────────────

it('closes online booking at an expired shop and shows the phone number', function () {
    [$shop] = shopInState('expired');
    $shop->forceFill(['slug' => 'jf-cuts', 'phone' => '+31301234567', 'name' => 'JF Cuts'])->save();
    $staff = staffWithHours($shop);
    $service = Service::factory()->create(['barbershop_id' => $shop->id]);

    $this->get('/jf-cuts')->assertOk()
        ->assertSee('Online boeken is tijdelijk niet beschikbaar')
        ->assertSee('+31301234567')
        ->assertDontSee('Afspraak maken');

    $this->get('/jf-cuts/boeken')->assertRedirect('/jf-cuts');

    app(CurrentShop::class)->set($shop);

    expect(fn () => app(AppointmentService::class)->book(new BookingData($shop, $service, $staff, at('2026-11-20 10:00'), new CustomerInput('Daan', '0612345678')), null))
        ->toThrow(BookingException::class, 'Online boeken is tijdelijk niet beschikbaar.');

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

it('keeps the booking page open in every writable state', function (string $state) {
    [$shop] = shopInState($state);
    $shop->forceFill(['slug' => 'jf-cuts'])->save();
    staffWithHours($shop);
    Service::factory()->create(['barbershop_id' => $shop->id]);

    $this->get('/jf-cuts')->assertOk()->assertSee('Afspraak maken');
    $this->get('/jf-cuts/boeken')->assertOk()->assertSee('Kies een dienst');
})->with(['trial', 'free', 'active', 'past_due']);

it('makes a suspended shop unavailable everywhere but loses no data', function () {
    [$shop, $owner] = shopInState('suspended');
    $shop->forceFill(['slug' => 'jf-cuts', 'name' => 'JF Cuts'])->save();
    Service::factory()->create(['barbershop_id' => $shop->id, 'name' => 'Knippen']);

    $this->get('/jf-cuts')->assertStatus(503)->assertSee('niet beschikbaar');
    $this->get('/jf-cuts/boeken')->assertStatus(503);
    $this->actingAs($owner)->get('/app')->assertForbidden()->assertSee('geblokkeerd')->assertSee('Er is niets verwijderd');
    $this->actingAs($owner)->get('/app/diensten')->assertForbidden();

    // Uitloggen kan nog
    $this->actingAs($owner)->post('/uitloggen')->assertRedirect('/');

    expect(Service::withoutGlobalScopes()->where('name', 'Knippen')->exists())->toBeTrue();
});

// ── Gratis start ─────────────────────────────────────────────────────────────────────────────────────────

it('shows no payment screens while billing is off', function () {
    config(['barbercuts.billing.enabled' => false]);
    [$shop, $owner] = shopWithOwner();

    $this->actingAs($owner)->get('/app/abonnement')->assertOk()->assertSee('Betalen staat tijdelijk uit')->assertDontSee('Abonnement starten');
    $this->actingAs($owner)->get('/app/meer')->assertOk()->assertSee('Betalen staat tijdelijk uit');
    $this->actingAs($owner)->get('/app')->assertOk()->assertDontSee('Alleen lezen')->assertDontSee('Proefperiode');
    $this->get('/prijzen')->assertSee('Tijdens de lancering gratis');
    $this->get('/')->assertSee('Tijdens de lancering gratis')->assertDontSee('dagen gratis');
});

it('shows the price and trial of the active plan once billing is on', function () {
    $this->get('/prijzen')->assertSee('€ 10,00')->assertSee('De eerste 14 dagen gratis');
    $this->get('/')->assertSee('€ 10,00 per maand')->assertSee('14 dagen gratis');

    Plan::current()->update(['price_cents' => 1250, 'trial_days' => 30]);

    $this->get('/prijzen')->assertSee('€ 12,50')->assertSee('De eerste 30 dagen gratis');
});

it('gives a new shop a trial from the active plan, or none while billing is off', function () {
    $owner = User::factory()->create();
    $shop = app(ShopSetupService::class)->createShop($owner, 'Nieuwe zaak', 'Utrecht', null, 'nieuwe-zaak');

    expect($shop->plan_id)->toBe(Plan::current()->id)
        ->and((int) round(now()->diffInDays($shop->trial_ends_at)))->toBe(14)
        ->and($shop->accessState())->toBe(AccessState::Trial);

    config(['barbercuts.billing.enabled' => false]);
    $second = app(ShopSetupService::class)->createShop(User::factory()->create(), 'Tweede zaak', 'Utrecht', null, 'tweede-zaak');

    expect($second->trial_ends_at)->toBeNull()->and($second->accessState())->toBe(AccessState::Free);
});

it('knows how many trial days are left', function () {
    [$shop] = shopInState('trial');

    expect($shop->trialDaysLeft())->toBe(10);

    $shop->forceFill(['trial_ends_at' => now()->subDay()])->save();
    expect($shop->trialDaysLeft())->toBeNull();
});
