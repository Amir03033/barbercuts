<?php

use App\Enums\AppointmentStatus;
use App\Livewire\Shop\Book;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\User;
use App\Notifications\AppointmentConfirmed;
use App\Support\CurrentShop;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/*
| De boekflow op /{slug}/boeken (§9.2). "Nu" is maandag 16 november 2026, 08:00.
*/

beforeEach(function () {
    $this->travelTo(at('2026-11-16 08:00'));

    [$this->shop] = shopWithOwner(['slug' => 'jf-cuts', 'name' => 'JF Cuts']);
    $this->sam = staffWithHours($this->shop, 'Sam');
    $this->service = Service::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Knippen', 'duration' => 30, 'price_cents' => 2500]);
    app(CurrentShop::class)->set($this->shop);

    $this->settings = fn (array $values) => ShopSetting::withoutGlobalScopes()->where('barbershop_id', $this->shop->id)->update($values);

    // Tot en met de stap "gegevens", met dinsdag 10:00 gekozen
    $this->toDetails = fn () => Livewire::test(Book::class)
        ->call('chooseService', $this->service->id)
        ->set('date', '2026-11-17')
        ->set('time', '10:00')
        ->call('confirmTime')
        ->assertSet('step', 'details');
});

it('shows the booking page with the services of the shop', function () {
    Service::factory()->inactive()->create(['barbershop_id' => $this->shop->id, 'name' => 'Oude dienst']);

    $this->get('/jf-cuts/boeken')->assertOk()
        ->assertSee('Kies een dienst')->assertSee('Knippen')->assertSee('€ 25,00')
        ->assertDontSee('Oude dienst');

    $this->get('/jf-cuts')->assertSee('Afspraak maken');
});

it('skips the barber step when the shop has one barber', function () {
    Livewire::test(Book::class)
        ->call('chooseService', $this->service->id)
        ->assertSet('step', 'time')
        ->assertSet('staffChoice', (string) $this->sam->id);
});

it('asks for a barber when there are several, with no preference first', function () {
    $mo = staffWithHours($this->shop, 'Mo', sortOrder: 1);

    Livewire::test(Book::class)
        ->call('chooseService', $this->service->id)
        ->assertSet('step', 'staff')
        ->assertSeeInOrder(['Geen voorkeur', 'Sam', 'Mo'])
        ->call('chooseStaff', (string) $mo->id)
        ->assertSet('step', 'time')
        ->assertSee('Bij Mo');
});

it('skips the barber step when the shop does not let customers choose', function () {
    staffWithHours($this->shop, 'Mo');
    ($this->settings)(['allow_staff_choice' => false]);

    Livewire::test(Book::class)
        ->call('chooseService', $this->service->id)
        ->assertSet('step', 'time')
        ->assertSet('staffChoice', 'any');
});

it('only offers barbers who do the chosen service', function () {
    $mo = staffWithHours($this->shop, 'Mo');
    $lars = staffWithHours($this->shop, 'Lars');
    DB::table('service_staff_member')->insert([
        ['service_id' => $this->service->id, 'staff_member_id' => $this->sam->id],
        ['service_id' => $this->service->id, 'staff_member_id' => $mo->id],
    ]);

    Livewire::test(Book::class)
        ->call('chooseService', $this->service->id)
        ->assertSee('Sam')->assertSee('Mo')->assertDontSee('Lars')
        ->call('chooseStaff', (string) $lars->id)
        ->assertSet('step', 'staff')
        ->assertSet('staffChoice', '');
});

it('refuses a service or barber of another shop', function () {
    [$other] = shopWithOwner();
    $foreignService = Service::factory()->create(['barbershop_id' => $other->id]);
    $foreignStaff = staffWithHours($other, 'Buurman');
    staffWithHours($this->shop, 'Mo');

    Livewire::test(Book::class)
        ->call('chooseService', $foreignService->id)
        ->assertSet('step', 'service')->assertSet('serviceId', null)
        ->call('chooseService', $this->service->id)
        ->call('chooseStaff', (string) $foreignStaff->id)
        ->assertSet('staffChoice', '');
});

it('preselects the first day with free times and groups the times', function () {
    $this->travelTo(at('2026-11-21 19:00'));   // zaterdagavond: vandaag is vol, zondag dicht

    Livewire::test(Book::class)
        ->call('chooseService', $this->service->id)
        ->assertSet('date', '2026-11-23')
        ->assertSeeInOrder(['Ochtend', '09:00', 'Middag', '12:00', '17:30'])
        ->assertDontSee('Avond');
});

it('loads more days on request, up to the maximum of the shop', function () {
    ($this->settings)(['max_days_ahead' => 20]);

    Livewire::test(Book::class)
        ->call('chooseService', $this->service->id)
        ->assertSet('daysShown', 14)
        ->assertSee('Meer dagen')
        ->call('showMoreDays')
        ->assertSet('daysShown', 21)
        ->assertDontSee('Meer dagen');
});

it('does not continue without a valid free time', function (string $time) {
    Appointment::factory()->create([
        'barbershop_id' => $this->shop->id, 'staff_member_id' => $this->sam->id,
        'starts_at' => at('2026-11-17 11:00'), 'ends_at' => at('2026-11-17 11:30'), 'blocked_until' => at('2026-11-17 11:30'),
    ]);

    Livewire::test(Book::class)
        ->call('chooseService', $this->service->id)
        ->set('date', '2026-11-17')
        ->set('time', $time)
        ->call('confirmTime')
        ->assertSet('step', 'time')
        ->assertHasErrors('time');
})->with(['', '11:00', '10:07', '23:00', 'abc']);

it('books as a guest with name and phone only and goes to the confirmation', function () {
    Notification::fake();

    ($this->toDetails)()
        ->assertSee('Sneller boeken met een account')
        ->assertSee('Zonder e-mail ontvang je geen bevestiging per mail.')
        ->set('name', 'Daan de Vries')
        ->set('phone', '06 12345678')
        ->set('notes', 'Fade 1,5')
        ->call('book')
        ->assertHasNoErrors();

    $appointment = Appointment::withoutGlobalScopes()->firstOrFail();

    expect($appointment->starts_at->toDateTimeString())->toBe('2026-11-17 09:00:00')
        ->and($appointment->staff_member_id)->toBe($this->sam->id)
        ->and($appointment->notes)->toBe('Fade 1,5')
        ->and(Customer::withoutGlobalScopes()->firstOrFail()->only(['name', 'phone', 'email', 'user_id']))
        ->toBe(['name' => 'Daan de Vries', 'phone' => '+31612345678', 'email' => null, 'user_id' => null]);

    Notification::assertNotSentTo(new AnonymousNotifiable, AppointmentConfirmed::class);

    $this->get($appointment->manageUrl())->assertOk();
});

it('redirects to the manage page as confirmation and mails when an address is given', function () {
    Notification::fake();

    $component = ($this->toDetails)()
        ->set('name', 'Daan')->set('phone', '0612345678')->set('email', 'daan@example.com')
        ->call('book');

    $appointment = Appointment::withoutGlobalScopes()->firstOrFail();

    $component->assertRedirect(url('/afspraak/'.$appointment->manage_token));
    expect(session('booked'))->toBe($appointment->manage_token);
    Notification::assertSentOnDemand(AppointmentConfirmed::class);
});

it('requires name and a valid phone number', function (array $input, string $field) {
    ($this->toDetails)()
        ->set('name', $input['name'] ?? 'Daan')
        ->set('phone', $input['phone'] ?? '0612345678')
        ->set('email', $input['email'] ?? '')
        ->call('book')
        ->assertHasErrors($field);

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'no phone' => [['phone' => ''], 'phone'],
    'invalid phone' => [['phone' => '12345'], 'phone'],
    'invalid email' => [['email' => 'geen-email'], 'email'],
]);

it('sends the customer back to the times when the slot was just taken', function () {
    $component = ($this->toDetails)()->set('name', 'Daan')->set('phone', '0612345678');

    Appointment::factory()->create([
        'barbershop_id' => $this->shop->id, 'staff_member_id' => $this->sam->id,
        'starts_at' => at('2026-11-17 10:00'), 'ends_at' => at('2026-11-17 10:30'), 'blocked_until' => at('2026-11-17 10:30'),
    ]);

    $component->call('book')
        ->assertSet('step', 'time')
        ->assertSet('time', '')
        ->assertSee('Dit tijdstip is net geboekt. Kies een andere tijd.');

    expect(Appointment::withoutGlobalScopes()->count())->toBe(1);
});

it('books nothing when the honeypot is filled in', function () {
    ($this->toDetails)()
        ->set('name', 'Bot')->set('phone', '0612345678')->set('website', 'https://spam.example')
        ->call('book')
        ->assertHasErrors('booking');

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

it('limits bookings per phone number', function () {
    foreach (['10:00', '11:00', '12:00', '13:00', '14:00'] as $time) {
        Livewire::test(Book::class)
            ->call('chooseService', $this->service->id)
            ->set('date', '2026-11-17')->set('time', $time)->call('confirmTime')
            ->set('name', 'Daan')->set('phone', '0612345678')
            ->call('book')->assertHasNoErrors();

        RateLimiter::clear('book-ip:127.0.0.1');
    }

    Livewire::test(Book::class)
        ->call('chooseService', $this->service->id)
        ->set('date', '2026-11-17')->set('time', '15:00')->call('confirmTime')
        ->set('name', 'Daan')->set('phone', '+31 6 12345678')
        ->call('book')
        ->assertHasErrors('booking')
        ->assertSee('veel afspraken gemaakt');

    expect(Appointment::withoutGlobalScopes()->count())->toBe(5);
});

it('limits bookings per ip address', function () {
    RateLimiter::increment('book-ip:127.0.0.1', 3600, 10);

    ($this->toDetails)()->set('name', 'Daan')->set('phone', '0612345678')->call('book')->assertHasErrors('booking');

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

it('requires an account when the shop does not allow guests', function () {
    ($this->settings)(['allow_guest_booking' => false]);

    ($this->toDetails)()
        ->assertSee('boek je met een account')
        ->set('name', 'Daan')->set('phone', '0612345678')
        ->call('book')
        ->assertHasErrors('booking');

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

it('prefills a logged in customer and links the booking to the account', function () {
    $user = User::factory()->create(['name' => 'Daan de Vries', 'email' => 'daan@example.com', 'phone' => null]);
    $this->actingAs($user);

    ($this->toDetails)()
        ->assertSet('name', 'Daan de Vries')->assertSet('email', 'daan@example.com')
        ->assertDontSee('Sneller boeken met een account')
        ->assertSee('Je boekt als')
        ->set('phone', '0612345678')
        ->call('book')
        ->assertHasNoErrors();

    expect(Customer::withoutGlobalScopes()->firstOrFail()->user_id)->toBe($user->id)
        // Het nummer wordt bewaard voor de volgende keer
        ->and($user->fresh()->phone)->toBe('+31612345678');
});

it('remembers the booking while the customer logs in or registers first', function () {
    ($this->toDetails)()->call('continueWithAccount', 'login')->assertRedirect(route('login'));

    $intended = session('url.intended');

    expect($intended)->toContain('/jf-cuts/boeken')
        ->toContain('dienst='.$this->service->id)
        ->toContain('dag=2026-11-17')
        ->toContain('tijd=10%3A00');
});

it('continues at the details step from the url after logging in', function () {
    $this->actingAs(User::factory()->create());

    Livewire::withQueryParams(['dienst' => $this->service->id, 'kapper' => (string) $this->sam->id, 'dag' => '2026-11-17', 'tijd' => '10:00'])
        ->test(Book::class)
        ->assertSet('step', 'details');

    // Een tijd die niet (meer) vrij is: terug naar de tijden
    Livewire::withQueryParams(['dienst' => $this->service->id, 'kapper' => (string) $this->sam->id, 'dag' => '2026-11-17', 'tijd' => '03:00'])
        ->test(Book::class)
        ->assertSet('step', 'time');
});

it('goes back one step at a time', function () {
    staffWithHours($this->shop, 'Mo');

    Livewire::test(Book::class)
        ->call('chooseService', $this->service->id)
        ->call('chooseStaff', 'any')
        ->set('date', '2026-11-17')->set('time', '10:00')->call('confirmTime')
        ->call('back')->assertSet('step', 'time')
        ->call('back')->assertSet('step', 'staff')
        ->call('back')->assertSet('step', 'service');
});

it('does not offer online booking for a shop that is not public', function () {
    $this->shop->forceFill(['is_public' => false])->save();

    $this->get('/jf-cuts/boeken')->assertNotFound();
});

it('never completes a booking as another status or in the past through the form', function () {
    ($this->toDetails)()->set('name', 'Daan')->set('phone', '0612345678')->set('step', 'done')->call('book');

    expect(Appointment::withoutGlobalScopes()->firstOrFail()->status)->toBe(AppointmentStatus::Scheduled);
});
