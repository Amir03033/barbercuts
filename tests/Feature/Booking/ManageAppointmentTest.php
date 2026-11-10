<?php

use App\Enums\AppointmentStatus;
use App\Enums\CancelledBy;
use App\Livewire\Account;
use App\Livewire\ManageAppointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\BookingData;
use App\Services\Booking\CustomerInput;
use App\Support\CurrentShop;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
| Beheerlink /afspraak/{token}, .ics en het klantaccount (§6.5, §9.2, §9.3). "Nu" is maandag 16 november 2026, 08:00.
*/

beforeEach(function () {
    Notification::fake();
    $this->travelTo(at('2026-11-16 08:00'));

    [$this->shop, $this->owner] = shopWithOwner(['name' => 'JF Cuts', 'slug' => 'jf-cuts', 'phone' => '+31301234567']);
    $this->sam = staffWithHours($this->shop, 'Sam');
    $this->service = Service::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Knippen', 'duration' => 30]);

    $this->bookFor = fn (string $time = '2026-11-17 10:00', ?User $actor = null, string $phone = '0612345678', ?string $email = 'daan@example.com', $shop = null, $service = null, $staff = null) => app(AppointmentService::class)->book(
        new BookingData($shop ?? $this->shop, $service ?? $this->service, $staff ?? $this->sam, at($time), new CustomerInput('Daan de Vries', $phone, $email)),
        $actor,
    );

    $this->appointment = ($this->bookFor)();

    // De beheerlink heeft geen tenant: begin zonder zaak, zoals een echt request.
    app(CurrentShop::class)->clear();
});

it('shows the appointment through the manage link without logging in', function () {
    $this->get('/afspraak/'.$this->appointment->manage_token)->assertOk()
        ->assertSee('JF Cuts')->assertSee('Knippen')->assertSee('Bij Sam')
        ->assertSee('Dinsdag 17 november')->assertSee('10:00 – 10:30')
        ->assertSee('Verplaatsen')->assertSee('Annuleren')->assertSee('Zet in je agenda');
});

it('gives a 404 for an unknown or malformed token', function (string $token) {
    $this->get('/afspraak/'.$token)->assertNotFound();
})->with([str_repeat('a', 40), 'kort', str_repeat('a', 41)]);

it('shows the confirmation right after booking', function () {
    $this->withSession(['booked' => $this->appointment->manage_token])
        ->get('/afspraak/'.$this->appointment->manage_token)
        ->assertSee('Je afspraak staat vast')
        ->assertSee('Je krijgt een bevestiging op daan@example.com.')
        ->assertSee('Bewaar deze afspraak in je account');
});

it('lets a guest reschedule to a free time, keeping the same barber', function () {
    staffWithHours($this->shop, 'Mo');
    ($this->bookFor)('2026-11-17 14:00', phone: '0687654321');
    app(CurrentShop::class)->clear();

    Livewire::test(ManageAppointment::class, ['token' => $this->appointment->manage_token])
        ->call('startReschedule')
        ->assertSet('date', '2026-11-17')
        ->assertSee('10:00')        // de eigen tijd telt niet als bezet
        ->assertDontSee('14:00')    // bezet bij Sam, ook al is Mo vrij
        ->set('time', '15:00')
        ->call('reschedule')
        ->assertHasNoErrors()
        ->assertSet('mode', 'view');

    expect($this->appointment->fresh()->starts_at->toDateTimeString())->toBe('2026-11-17 14:00:00')
        ->and($this->appointment->fresh()->staff_member_id)->toBe($this->sam->id);
});

it('does not reschedule to a time that is taken', function () {
    ($this->bookFor)('2026-11-17 14:00', phone: '0687654321');
    app(CurrentShop::class)->clear();

    Livewire::test(ManageAppointment::class, ['token' => $this->appointment->manage_token])
        ->call('startReschedule')
        ->set('time', '14:00')
        ->call('reschedule')
        ->assertHasErrors('time')
        ->assertSet('mode', 'reschedule');

    expect($this->appointment->fresh()->starts_at->toDateTimeString())->toBe('2026-11-17 09:00:00');
});

it('lets a guest cancel within the limit', function () {
    Livewire::test(ManageAppointment::class, ['token' => $this->appointment->manage_token])
        ->call('cancel')
        ->assertSee('Geannuleerd')
        ->assertSee('Nieuwe afspraak maken')
        ->assertDontSee('Verplaatsen');

    expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and($this->appointment->fresh()->cancelled_by)->toBe(CancelledBy::Customer);
});

it('tells the customer to call after the limit and refuses changes', function () {
    ShopSetting::withoutGlobalScopes()->where('barbershop_id', $this->shop->id)->update(['customer_cancel_limit' => 1440, 'customer_reschedule_limit' => 1440]);
    $this->travelTo(at('2026-11-17 08:00'));

    Livewire::test(ManageAppointment::class, ['token' => $this->appointment->manage_token])
        ->assertSee('Bel de zaak om je afspraak te wijzigen.')
        ->assertSee('Bel JF Cuts')
        ->assertDontSee('Verplaatsen')
        ->call('cancel')
        ->call('startReschedule')->set('time', '15:00')->call('reschedule')
        ->assertHasErrors('time');

    expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and($this->appointment->fresh()->starts_at->toDateTimeString())->toBe('2026-11-17 09:00:00');
});

it('lets the owner change an appointment through the link after the customer limit', function () {
    ShopSetting::withoutGlobalScopes()->where('barbershop_id', $this->shop->id)->update(['customer_cancel_limit' => 1440]);
    $this->travelTo(at('2026-11-17 08:00'));
    $this->actingAs($this->owner);

    Livewire::test(ManageAppointment::class, ['token' => $this->appointment->manage_token])->call('cancel');

    expect($this->appointment->fresh()->cancelled_by)->toBe(CancelledBy::Staff);
});

it('only ever touches the appointment of the token', function () {
    $other = ($this->bookFor)('2026-11-17 14:00', phone: '0687654321');
    app(CurrentShop::class)->clear();

    Livewire::test(ManageAppointment::class, ['token' => $this->appointment->manage_token])->call('cancel');

    expect($other->fresh()->status)->toBe(AppointmentStatus::Scheduled);

    Livewire::test(ManageAppointment::class, ['token' => $this->appointment->manage_token])->set('token', $other->manage_token);
})->throws(Exception::class);

it('downloads the appointment as an ics file in utc', function () {
    $response = $this->get('/afspraak/'.$this->appointment->manage_token.'/agenda.ics')->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="afspraak-jf-cuts.ics"');

    expect($response->getContent())
        ->toContain("BEGIN:VEVENT\r\n")
        ->toContain('DTSTART:20261117T090000Z')
        ->toContain('DTEND:20261117T093000Z')
        ->toContain('SUMMARY:Knippen bij JF Cuts')
        ->toContain('/afspraak/'.$this->appointment->manage_token);

    $this->get('/afspraak/'.str_repeat('x', 40).'/agenda.ics')->assertNotFound();
});

// ── Gast → account (§6.5) ────────────────────────────────────────────────────────────────────────────────

it('lets a guest create an account from the confirmation and links this appointment', function () {
    Livewire::test(ManageAppointment::class, ['token' => $this->appointment->manage_token])
        ->assertSet('accountEmail', 'daan@example.com')
        ->set('accountPassword', 'geheim-wachtwoord')
        ->call('createAccount')
        ->assertHasNoErrors()
        ->assertDontSee('Bewaar deze afspraak in je account')
        ->assertSee('Al mijn afspraken');

    $user = User::where('email', 'daan@example.com')->firstOrFail();

    expect($user->name)->toBe('Daan de Vries')
        ->and($user->phone)->toBe('+31612345678')
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(Customer::withoutGlobalScopes()->findOrFail($this->appointment->customer_id)->user_id)->toBe($user->id);

    $this->assertAuthenticatedAs($user);
});

it('does not link appointments at other shops before the e-mail address is verified', function () {
    [$otherShop] = shopWithOwner(['name' => 'Andere Kapper']);
    $otherStaff = staffWithHours($otherShop, 'Mo');
    $otherService = Service::factory()->create(['barbershop_id' => $otherShop->id]);
    $elsewhere = ($this->bookFor)('2026-11-18 10:00', shop: $otherShop, service: $otherService, staff: $otherStaff);
    app(CurrentShop::class)->clear();

    Livewire::test(ManageAppointment::class, ['token' => $this->appointment->manage_token])
        ->set('accountPassword', 'geheim-wachtwoord')
        ->call('createAccount');

    $user = User::where('email', 'daan@example.com')->firstOrFail();
    $otherCustomer = fn () => Customer::withoutGlobalScopes()->findOrFail($elsewhere->customer_id);

    // Iemand kan een account maken met andermans adres: pas na bevestiging komen andere afspraken mee.
    expect($otherCustomer()->user_id)->toBeNull();
    Livewire::actingAs($user)->test(Account::class)->assertSee('JF Cuts')->assertDontSee('Andere Kapper');

    $user->markEmailAsVerified();
    event(new Verified($user));

    expect($otherCustomer()->user_id)->toBe($user->id);
    Livewire::actingAs($user)->test(Account::class)->assertSee('JF Cuts')->assertSee('Andere Kapper');
});

it('never links customers by e-mail that already belong to another account', function () {
    $someoneElse = User::factory()->create();
    Customer::withoutGlobalScopes()->whereKey($this->appointment->customer_id)->update(['user_id' => $someoneElse->id]);

    $user = User::factory()->create(['email' => 'daan@example.com']);
    event(new Verified($user));

    expect(Customer::withoutGlobalScopes()->findOrFail($this->appointment->customer_id)->user_id)->toBe($someoneElse->id);
});

it('asks to log in when the address already has an account', function () {
    User::factory()->create(['email' => 'daan@example.com']);

    Livewire::test(ManageAppointment::class, ['token' => $this->appointment->manage_token])
        ->set('accountPassword', 'geheim-wachtwoord')
        ->call('createAccount')
        ->assertHasErrors('accountEmail')
        ->assertSee('Log in om deze afspraak te bewaren');

    expect(Customer::withoutGlobalScopes()->findOrFail($this->appointment->customer_id)->user_id)->toBeNull();
});

it('lets a logged in customer save a guest appointment to the account', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(ManageAppointment::class, ['token' => $this->appointment->manage_token])
        ->assertSee('Bewaar in mijn account')
        ->call('saveToAccount')
        ->assertSee('Al mijn afspraken');

    expect(Customer::withoutGlobalScopes()->findOrFail($this->appointment->customer_id)->user_id)->toBe($user->id);
});

it('does not offer an account for an appointment that already belongs to one', function () {
    $owner = User::factory()->create();
    Customer::withoutGlobalScopes()->whereKey($this->appointment->customer_id)->update(['user_id' => $owner->id]);

    Livewire::test(ManageAppointment::class, ['token' => $this->appointment->manage_token])
        ->assertDontSee('Bewaar deze afspraak in je account')
        ->call('createAccount')
        ->assertForbidden();

    Livewire::actingAs(User::factory()->create())->test(ManageAppointment::class, ['token' => $this->appointment->manage_token])
        ->assertDontSee('Bewaar in mijn account')
        ->call('saveToAccount')
        ->assertForbidden();
});

// ── Klantaccount (§9.3) ──────────────────────────────────────────────────────────────────────────────────

it('requires a login for the account page', function () {
    $this->get('/account')->assertRedirect('/inloggen');
});

it('shows the own appointments at all shops and nothing of other customers', function () {
    $user = User::factory()->create();
    [$otherShop] = shopWithOwner(['name' => 'Andere Kapper']);
    $otherStaff = staffWithHours($otherShop, 'Mo');
    $otherService = Service::factory()->create(['barbershop_id' => $otherShop->id, 'name' => 'Baard trimmen']);

    ($this->bookFor)('2026-11-18 10:00', actor: $user, phone: '0687654321', email: null);
    ($this->bookFor)('2026-11-19 11:00', actor: $user, phone: '0687654321', email: null, shop: $otherShop, service: $otherService, staff: $otherStaff);
    app(CurrentShop::class)->clear();

    $this->actingAs($user)->get('/account')->assertOk()
        ->assertSeeInOrder(['Komende afspraken', 'JF Cuts', 'Andere Kapper'])
        ->assertSee('Baard trimmen')
        // De afspraak van de gast Daan (dinsdag 10:00) hoort niet bij dit account
        ->assertDontSee('Morgen 10:00');
});

it('moves past and cancelled appointments to the history with a rebook link', function () {
    $user = User::factory()->create();
    $past = ($this->bookFor)('2026-11-16 09:00', actor: $user, phone: '0687654321');
    $cancelled = ($this->bookFor)('2026-11-18 10:00', actor: $user, phone: '0687654321');
    app(AppointmentService::class)->cancel($cancelled, $user);
    app(CurrentShop::class)->clear();

    $this->travelTo(at('2026-11-16 12:00'));

    $this->actingAs($user)->get('/account')
        ->assertSee('Geen komende afspraken')
        ->assertSeeInOrder(['Geschiedenis', 'Geannuleerd', 'Geweest'])
        ->assertSee('/jf-cuts/boeken?dienst='.$this->service->id.'&amp;kapper='.$this->sam->id, false);
});
