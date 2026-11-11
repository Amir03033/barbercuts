<?php

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\CancelledBy;
use App\Enums\DepositStatus;
use App\Enums\DepositType;
use App\Enums\PaymentMethod;
use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Events\AppointmentRescheduled;
use App\Exceptions\BookingException;
use App\Jobs\CancelUnpaidDeposit;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\User;
use App\Notifications\AppointmentMessage;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\BookingData;
use App\Services\Booking\CustomerInput;
use App\Services\Booking\DepositCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
| Boeken en statussen (docs/BOUWOPDRACHT.md §7.3, §7.4 en §12). "Nu" is maandag 16 november 2026, 08:00.
*/

beforeEach(function () {
    $this->travelTo(at('2026-11-16 08:00'));

    [$this->shop, $this->owner] = shopWithOwner();
    $this->sam = staffWithHours($this->shop, 'Sam');
    $this->service = Service::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Knippen', 'duration' => 30, 'price_cents' => 2500, 'buffer_after' => 10]);
    $this->appointments = app(AppointmentService::class);
    $this->guest = new CustomerInput('Daan de Vries', '06 12345678', 'daan@example.com');

    $this->data = fn (array $overrides = []) => new BookingData(
        shop: $overrides['shop'] ?? $this->shop,
        service: $overrides['service'] ?? $this->service,
        staff: array_key_exists('staff', $overrides) ? $overrides['staff'] : $this->sam,
        startsAt: at($overrides['at'] ?? '2026-11-17 10:00'),
        customer: $overrides['customer'] ?? $this->guest,
        source: $overrides['source'] ?? AppointmentSource::Online,
        notes: $overrides['notes'] ?? null,
    );

    $this->settings = fn (array $values) => ShopSetting::withoutGlobalScopes()->where('barbershop_id', $this->shop->id)->update($values);
});

// ── Boeken ───────────────────────────────────────────────────────────────────────────────────────────────

it('books an appointment for a guest with a snapshot of the service', function () {
    $appointment = $this->appointments->book(($this->data)(['notes' => ' Fade 1,5 ']), null);

    expect($appointment->barbershop_id)->toBe($this->shop->id)
        ->and($appointment->staff_member_id)->toBe($this->sam->id)
        ->and($appointment->service_name)->toBe('Knippen')
        ->and($appointment->duration)->toBe(30)
        ->and($appointment->price_cents)->toBe(2500)
        ->and($appointment->starts_at->toDateTimeString())->toBe('2026-11-17 09:00:00')      // UTC
        ->and($appointment->ends_at->toDateTimeString())->toBe('2026-11-17 09:30:00')        // zonder buffer
        ->and($appointment->blocked_until->toDateTimeString())->toBe('2026-11-17 09:40:00')  // met buffer
        ->and($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->source)->toBe(AppointmentSource::Online)
        ->and($appointment->notes)->toBe('Fade 1,5')
        ->and($appointment->manage_token)->toHaveLength(40)
        ->and($appointment->deposit_cents)->toBe(0)
        ->and($appointment->deposit_status)->toBe(DepositStatus::None)
        ->and($appointment->created_by_user_id)->toBeNull();

    $customer = Customer::withoutGlobalScopes()->findOrFail($appointment->customer_id);

    expect($customer->phone)->toBe('+31612345678')
        ->and($customer->name)->toBe('Daan de Vries')
        ->and($customer->user_id)->toBeNull();
});

it('keeps the snapshot when the service changes later', function () {
    $appointment = $this->appointments->book(($this->data)(), null);

    $this->service->update(['name' => 'Knippen deluxe', 'price_cents' => 4000, 'duration' => 60]);

    expect($appointment->fresh()->only(['service_name', 'price_cents', 'duration']))
        ->toBe(['service_name' => 'Knippen', 'price_cents' => 2500, 'duration' => 30]);
});

it('fails the second booking of the same slot', function () {
    $first = ($this->data)();
    $second = ($this->data)(['customer' => new CustomerInput('Yusuf', '0687654321')]);

    $this->appointments->book($first, null);

    expect(fn () => $this->appointments->book($second, null))
        ->toThrow(BookingException::class, 'Dit tijdstip is net geboekt. Kies een andere tijd.');

    expect(Appointment::withoutGlobalScopes()->count())->toBe(1)
        // Ook de klant van de mislukte boeking is niet aangemaakt
        ->and(Customer::withoutGlobalScopes()->count())->toBe(1);
});

it('fails a booking that overlaps an existing appointment or its buffer', function (string $time) {
    $this->appointments->book(($this->data)(), null);

    expect(fn () => $this->appointments->book(($this->data)(['at' => $time]), null))->toThrow(BookingException::class);
})->with(['2026-11-17 09:45', '2026-11-17 10:15', '2026-11-17 10:30']);

it('locks the rows of the barbers involved while booking', function () {
    staffWithHours($this->shop, 'Mo');

    DB::enableQueryLog();
    $this->appointments->book(($this->data)(['staff' => null]), null);
    DB::disableQueryLog();

    $lock = collect(DB::getQueryLog())->pluck('query')->first(fn (string $sql) => str_contains($sql, 'for update'));

    expect($lock)->toContain('staff_members')->toContain('order by `id` asc');
});

it('refuses times outside the grid, in the past, within the notice or too far ahead for online bookings', function (string $time) {
    ($this->settings)(['min_notice' => 120, 'max_days_ahead' => 7]);

    expect(fn () => $this->appointments->book(($this->data)(['at' => $time]), null))->toThrow(BookingException::class);
})->with([
    'off the grid' => '2026-11-17 10:07',
    'in the past' => '2026-11-14 10:00',
    'within the notice' => '2026-11-16 09:30',
    'too far ahead' => '2026-11-24 10:00',
    'outside working hours' => '2026-11-17 20:00',
    'on a day off' => '2026-11-22 10:00',
]);

it('refuses a service that is inactive or belongs to another shop', function (Closure $service) {
    expect(fn () => $this->appointments->book(($this->data)(['service' => $service($this)]), null))
        ->toThrow(BookingException::class, 'Deze dienst is niet (meer) beschikbaar.');
})->with([
    'inactive' => [fn ($test) => Service::factory()->inactive()->create(['barbershop_id' => $test->shop->id])],
    'other shop' => [fn ($test) => Service::factory()->create()],
]);

it('refuses a barber who is inactive, from another shop or does not do the service', function (Closure $staff) {
    expect(fn () => $this->appointments->book(($this->data)(['staff' => $staff($this)]), null))
        ->toThrow(BookingException::class, 'Deze kapper is niet beschikbaar voor deze dienst.');
})->with([
    'inactive' => [fn ($test) => tap(staffWithHours($test->shop, 'Weg'), fn ($staff) => $staff->update(['is_active' => false]))],
    'other shop' => [fn ($test) => staffWithHours(shopWithOwner()[0], 'Buurman')],
    'does not do the service' => [function ($test) {
        $mo = staffWithHours($test->shop, 'Mo');
        DB::table('service_staff_member')->insert(['service_id' => $test->service->id, 'staff_member_id' => $test->sam->id]);

        return $mo;
    }],
]);

it('refuses online bookings for a shop that is not public', function () {
    $this->shop->forceFill(['is_public' => false])->save();

    expect(fn () => $this->appointments->book(($this->data)(), null))
        ->toThrow(BookingException::class, 'Online boeken is tijdelijk niet beschikbaar.');
});

it('refuses guests when the shop requires an account', function () {
    ($this->settings)(['allow_guest_booking' => false]);

    expect(fn () => $this->appointments->book(($this->data)(), null))->toThrow(BookingException::class);

    $appointment = $this->appointments->book(($this->data)(), User::factory()->create());

    expect($appointment->exists)->toBeTrue();
});

it('assigns no preference fairly', function () {
    $mo = staffWithHours($this->shop, 'Mo', sortOrder: 1);

    $first = $this->appointments->book(($this->data)(['staff' => null, 'at' => '2026-11-17 10:00']), null);
    $second = $this->appointments->book(($this->data)(['staff' => null, 'at' => '2026-11-17 11:00', 'customer' => new CustomerInput('Yusuf', '0687654321')]), null);
    $third = $this->appointments->book(($this->data)(['staff' => null, 'at' => '2026-11-17 10:00', 'customer' => new CustomerInput('Lars', '0611111111')]), null);

    expect($first->staff_member_id)->toBe($this->sam->id)     // gelijk: laagste sort_order
        ->and($second->staff_member_id)->toBe($mo->id)         // Sam heeft al 30 minuten
        ->and($third->staff_member_id)->toBe($mo->id);         // Sam is om 10:00 bezet
});

// ── Klant koppelen (§6.5) ────────────────────────────────────────────────────────────────────────────────

it('reuses an existing customer with the same phone number and only fills empty fields', function () {
    $existing = Customer::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'D. de Vries', 'phone' => '+31612345678', 'email' => null]);

    $appointment = $this->appointments->book(($this->data)(['customer' => new CustomerInput('Daan', '+31 6 1234 5678', 'Daan@Example.com')]), null);

    expect($appointment->customer_id)->toBe($existing->id)
        ->and($existing->fresh()->name)->toBe('D. de Vries')          // niet overschreven
        ->and($existing->fresh()->email)->toBe('daan@example.com')    // was leeg
        ->and(Customer::withoutGlobalScopes()->count())->toBe(1);
});

it('normalises phone numbers to E.164', function (string $input) {
    $appointment = $this->appointments->book(($this->data)(['customer' => new CustomerInput('Daan', $input)]), null);

    expect(Customer::withoutGlobalScopes()->findOrFail($appointment->customer_id)->phone)->toBe('+31612345678');
})->with(['0612345678', '06 12 34 56 78', '06-12345678', '+31612345678', '0031612345678', '+31 (0)6 12345678']);

it('refuses an invalid phone number or an empty name', function (CustomerInput $customer) {
    expect(fn () => $this->appointments->book(($this->data)(['customer' => $customer]), null))->toThrow(BookingException::class);

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
})->with([
    'too short' => [new CustomerInput('Daan', '12345')],
    'letters' => [new CustomerInput('Daan', 'geen nummer')],
    'no name' => [new CustomerInput('  ', '0612345678')],
]);

it('keeps customers with the same phone number separate per shop', function () {
    [$other] = shopWithOwner();
    Customer::factory()->create(['barbershop_id' => $other->id, 'phone' => '+31612345678']);

    $appointment = $this->appointments->book(($this->data)(), null);

    expect(Customer::withoutGlobalScopes()->findOrFail($appointment->customer_id)->barbershop_id)->toBe($this->shop->id)
        ->and(Customer::withoutGlobalScopes()->count())->toBe(2);
});

it('links the customer to the logged in user who books for himself', function () {
    $user = User::factory()->create();

    $first = $this->appointments->book(($this->data)(), $user);
    // Tweede boeking met een ander nummer: toch dezelfde klant, want één klantrecord per account per zaak
    $second = $this->appointments->book(($this->data)(['at' => '2026-11-18 10:00', 'customer' => new CustomerInput('Daan', '0687654321')]), $user);

    expect(Customer::withoutGlobalScopes()->findOrFail($first->customer_id)->user_id)->toBe($user->id)
        ->and($second->customer_id)->toBe($first->customer_id)
        ->and($first->created_by_user_id)->toBe($user->id);
});

// ── Kanalen en rechten ───────────────────────────────────────────────────────────────────────────────────

it('lets the owner book for a customer without an account in any calendar', function () {
    $mo = staffWithHours($this->shop, 'Mo');

    $appointment = $this->appointments->book(($this->data)(['staff' => $mo, 'source' => AppointmentSource::Staff]), $this->owner);

    expect($appointment->staff_member_id)->toBe($mo->id)
        ->and($appointment->source)->toBe(AppointmentSource::Staff)
        ->and($appointment->created_by_user_id)->toBe($this->owner->id)
        // De klant wordt niet aan de eigenaar gekoppeld
        ->and(Customer::withoutGlobalScopes()->findOrFail($appointment->customer_id)->user_id)->toBeNull();
});

it('lets the owner book an existing customer, but not a customer of another shop', function () {
    $customer = Customer::factory()->create(['barbershop_id' => $this->shop->id]);
    $stranger = Customer::factory()->create();

    $appointment = $this->appointments->book(($this->data)(['customer' => $customer, 'source' => AppointmentSource::Staff]), $this->owner);

    expect($appointment->customer_id)->toBe($customer->id)
        ->and(fn () => $this->appointments->book(($this->data)(['customer' => $stranger, 'at' => '2026-11-18 10:00', 'source' => AppointmentSource::Staff]), $this->owner))
        ->toThrow(AuthorizationException::class);
});

it('lets a barber book only in the own calendar', function () {
    [$barber, $barberStaff] = barberIn($this->shop);
    setWorkingHours($barberStaff);

    $own = $this->appointments->book(($this->data)(['staff' => $barberStaff, 'source' => AppointmentSource::Staff]), $barber);
    $noPreference = $this->appointments->book(($this->data)(['staff' => null, 'at' => '2026-11-17 11:00', 'source' => AppointmentSource::Staff]), $barber);

    expect($own->staff_member_id)->toBe($barberStaff->id)
        ->and($noPreference->staff_member_id)->toBe($barberStaff->id)
        ->and(fn () => $this->appointments->book(($this->data)(['staff' => $this->sam, 'at' => '2026-11-17 12:00', 'source' => AppointmentSource::Staff]), $barber))
        ->toThrow(AuthorizationException::class);
});

it('does not let outsiders book as staff', function (?Closure $actor) {
    expect(fn () => $this->appointments->book(($this->data)(['source' => AppointmentSource::Staff]), $actor ? $actor() : null))
        ->toThrow(AuthorizationException::class);
})->with([
    'guest' => [null],
    'customer' => [fn () => User::factory()->create()],
    'owner of another shop' => [fn () => shopWithOwner()[1]],
]);

it('lets staff book a walk-in right now, off the grid and without notice', function () {
    $this->travelTo(at('2026-11-17 10:07'));
    ($this->settings)(['min_notice' => 240]);

    $appointment = $this->appointments->book(($this->data)(['at' => '2026-11-17 10:07', 'source' => AppointmentSource::WalkIn]), $this->owner);

    expect($appointment->source)->toBe(AppointmentSource::WalkIn)
        ->and($appointment->starts_at->toDateTimeString())->toBe('2026-11-17 09:07:00');
});

// ── Events en bevestiging ────────────────────────────────────────────────────────────────────────────────

it('dispatches the booked event and mails the confirmation with the manage link', function () {
    Notification::fake();
    Event::fakeExcept([AppointmentBooked::class]);

    $appointment = $this->appointments->book(($this->data)(), null);

    Notification::assertSentTo(new AnonymousNotifiable, AppointmentMessage::class, function (AppointmentMessage $notification, array $channels, AnonymousNotifiable $notifiable) use ($appointment) {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === 'daan@example.com'
            && $mail->subject === "Je afspraak bij {$this->shop->name}: Morgen 10:00"
            && $mail->actionUrl === url('/afspraak/'.$appointment->manage_token)
            && str_contains(implode(' ', $mail->introLines), 'Dinsdag 17 november om 10:00');
    });
});

it('does not mail a customer without an e-mail address', function () {
    Notification::fake();

    $this->appointments->book(($this->data)(['customer' => new CustomerInput('Daan', '0612345678')]), null);

    Notification::assertNotSentTo(new AnonymousNotifiable, AppointmentMessage::class);
});

it('does not dispatch events when the booking fails', function () {
    Event::fake([AppointmentBooked::class]);

    try {
        $this->appointments->book(($this->data)(['at' => '2026-11-17 20:00']), null);
    } catch (BookingException) {
    }

    Event::assertNotDispatched(AppointmentBooked::class);
});

// ── Verplaatsen ──────────────────────────────────────────────────────────────────────────────────────────

it('reschedules an appointment and ignores its own slot', function () {
    Event::fake([AppointmentRescheduled::class]);
    $appointment = $this->appointments->book(($this->data)(), null);

    // 10:15 overlapt met de eigen afspraak van 10:00–10:40 en moet toch kunnen
    $this->appointments->reschedule($appointment, at('2026-11-17 10:15'), null, null);

    expect($appointment->fresh()->starts_at->toDateTimeString())->toBe('2026-11-17 09:15:00')
        ->and($appointment->fresh()->ends_at->toDateTimeString())->toBe('2026-11-17 09:45:00')
        ->and($appointment->fresh()->blocked_until->toDateTimeString())->toBe('2026-11-17 09:55:00')
        ->and($appointment->fresh()->staff_member_id)->toBe($this->sam->id);

    Event::assertDispatched(AppointmentRescheduled::class, fn (AppointmentRescheduled $event) => $event->previousStartsAt->toDateTimeString() === '2026-11-17 09:00:00');
});

it('does not reschedule onto another appointment', function () {
    $appointment = $this->appointments->book(($this->data)(), null);
    $this->appointments->book(($this->data)(['at' => '2026-11-17 12:00', 'customer' => new CustomerInput('Yusuf', '0687654321')]), null);

    expect(fn () => $this->appointments->reschedule($appointment, at('2026-11-17 12:15'), null, null))
        ->toThrow(BookingException::class, 'Dit tijdstip is net geboekt');

    expect($appointment->fresh()->starts_at->toDateTimeString())->toBe('2026-11-17 09:00:00');
});

it('lets a customer reschedule only until the limit, staff always', function () {
    ($this->settings)(['customer_reschedule_limit' => 120]);
    $appointment = $this->appointments->book(($this->data)(), null);

    $this->travelTo(at('2026-11-17 08:01'));   // 1 uur 59 voor de afspraak

    expect(fn () => $this->appointments->reschedule($appointment, at('2026-11-17 15:00'), null, null))
        ->toThrow(BookingException::class, 'Bel de zaak');

    $this->appointments->reschedule($appointment, at('2026-11-17 15:00'), null, $this->owner);

    expect($appointment->fresh()->starts_at->toDateTimeString())->toBe('2026-11-17 14:00:00');
});

it('lets the owner move an appointment to another barber, a barber only within the own calendar', function () {
    [$barber, $barberStaff] = barberIn($this->shop);
    setWorkingHours($barberStaff);
    $appointment = $this->appointments->book(($this->data)(['staff' => $barberStaff]), null);

    expect(fn () => $this->appointments->reschedule($appointment, at('2026-11-17 11:00'), $this->sam, $barber))
        ->toThrow(AuthorizationException::class);

    $this->appointments->reschedule($appointment, at('2026-11-17 11:00'), null, $barber);
    expect($appointment->fresh()->staff_member_id)->toBe($barberStaff->id);

    $this->appointments->reschedule($appointment, at('2026-11-17 12:00'), $this->sam, $this->owner);
    expect($appointment->fresh()->staff_member_id)->toBe($this->sam->id);
});

it('does not let a barber touch appointments in a colleague\'s calendar', function () {
    [$barber] = barberIn($this->shop);
    $appointment = $this->appointments->book(($this->data)(), null);
    $this->travelTo(at('2026-11-17 10:05'));

    expect(fn () => $this->appointments->reschedule($appointment, at('2026-11-17 15:00'), null, $barber))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->appointments->cancel($appointment, $barber))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->appointments->complete($appointment, $barber, 2500))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->appointments->markNoShow($appointment, $barber))->toThrow(AuthorizationException::class);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('reschedules with the duration of the appointment itself, not the current duration of the service', function () {
    $appointment = $this->appointments->book(($this->data)(['at' => '2026-11-17 17:00']), null);
    $this->service->update(['duration' => 120]);

    $this->appointments->reschedule($appointment, at('2026-11-17 17:30'), null, null);

    expect($appointment->fresh()->ends_at->toDateTimeString())->toBe('2026-11-17 17:00:00');
});

// ── Annuleren ────────────────────────────────────────────────────────────────────────────────────────────

it('lets a customer cancel until the limit and frees the slot', function () {
    Event::fake([AppointmentCancelled::class]);
    $appointment = $this->appointments->book(($this->data)(), null);

    $this->appointments->cancel($appointment, null, 'Ziek');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and($appointment->fresh()->cancelled_by)->toBe(CancelledBy::Customer)
        ->and($appointment->fresh()->cancellation_reason)->toBe('Ziek')
        ->and($appointment->fresh()->cancelled_at)->not->toBeNull();

    Event::assertDispatched(AppointmentCancelled::class);

    // Het tijdstip is weer vrij
    $again = $this->appointments->book(($this->data)(['customer' => new CustomerInput('Yusuf', '0687654321')]), null);
    expect($again->exists)->toBeTrue();
});

it('does not let a customer cancel after the limit, but staff can', function () {
    ($this->settings)(['customer_cancel_limit' => 1440]);
    $appointment = $this->appointments->book(($this->data)(), null);

    $this->travelTo(at('2026-11-16 10:01'));   // minder dan 24 uur vooraf

    expect(fn () => $this->appointments->cancel($appointment, null))->toThrow(BookingException::class, 'Bel de zaak');

    $this->appointments->cancel($appointment, $this->owner);

    expect($appointment->fresh()->cancelled_by)->toBe(CancelledBy::Staff);
});

// ── Voltooien, no-show, ongedaan maken ───────────────────────────────────────────────────────────────────

it('completes an appointment that has started and records the payment', function () {
    $appointment = $this->appointments->book(($this->data)(), null);
    $this->travelTo(at('2026-11-17 10:20'));

    $this->appointments->complete($appointment, $this->owner, 2500, 200, PaymentMethod::Pin);

    $fresh = $appointment->fresh();

    expect($fresh->status)->toBe(AppointmentStatus::Completed)
        ->and($fresh->amount_paid_cents)->toBe(2500)
        ->and($fresh->tip_cents)->toBe(200)
        ->and($fresh->payment_method)->toBe(PaymentMethod::Pin)
        ->and($fresh->completed_at->toDateTimeString())->toBe('2026-11-17 09:20:00')
        ->and(Customer::withoutGlobalScopes()->findOrFail($fresh->customer_id)->last_visit_at->toDateTimeString())->toBe('2026-11-17 09:00:00');
});

it('does not complete or mark no-show before the appointment has started', function () {
    $appointment = $this->appointments->book(($this->data)(), null);

    expect(fn () => $this->appointments->complete($appointment, $this->owner, 2500))->toThrow(BookingException::class, 'nog niet begonnen')
        ->and(fn () => $this->appointments->markNoShow($appointment, $this->owner))->toThrow(BookingException::class, 'nog niet begonnen');
});

it('refuses a negative amount', function () {
    $appointment = $this->appointments->book(($this->data)(), null);
    $this->travelTo(at('2026-11-17 10:20'));

    expect(fn () => $this->appointments->complete($appointment, $this->owner, -1))->toThrow(BookingException::class)
        ->and(fn () => $this->appointments->complete($appointment, $this->owner, 2500, -1))->toThrow(BookingException::class);
});

it('marks a no-show, counts it for the customer and frees nothing in the past', function () {
    $appointment = $this->appointments->book(($this->data)(), null);
    $this->travelTo(at('2026-11-17 10:20'));

    $this->appointments->markNoShow($appointment, $this->owner);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::NoShow)
        ->and(Customer::withoutGlobalScopes()->findOrFail($appointment->customer_id)->no_show_count)->toBe(1);
});

it('undoes a completion within 24 hours and clears the amounts', function () {
    $appointment = $this->appointments->book(($this->data)(), null);
    $this->travelTo(at('2026-11-17 10:20'));
    $this->appointments->complete($appointment, $this->owner, 2500, 200, PaymentMethod::Cash);

    $this->travelTo(at('2026-11-18 10:19'));
    $this->appointments->undoComplete($appointment, $this->owner);

    $fresh = $appointment->fresh();

    expect($fresh->status)->toBe(AppointmentStatus::Scheduled)
        ->and($fresh->amount_paid_cents)->toBeNull()
        ->and($fresh->tip_cents)->toBeNull()
        ->and($fresh->payment_method)->toBeNull()
        ->and($fresh->completed_at)->toBeNull()
        ->and(Customer::withoutGlobalScopes()->findOrFail($fresh->customer_id)->last_visit_at)->toBeNull();
});

it('does not undo a completion after 24 hours', function () {
    $appointment = $this->appointments->book(($this->data)(), null);
    $this->travelTo(at('2026-11-17 10:20'));
    $this->appointments->complete($appointment, $this->owner, 2500);

    $this->travelTo(at('2026-11-18 10:21'));

    expect(fn () => $this->appointments->undoComplete($appointment, $this->owner))->toThrow(BookingException::class, '24 uur');
    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Completed);
});

it('refuses every transition that is not allowed', function (AppointmentStatus $from, string $action) {
    $appointment = $this->appointments->book(($this->data)(), null);
    $this->travelTo(at('2026-11-17 10:20'));
    $appointment->forceFill(['status' => $from, 'completed_at' => $from === AppointmentStatus::Completed ? now() : null])->save();

    $call = match ($action) {
        'reschedule' => fn () => $this->appointments->reschedule($appointment, at('2026-11-17 15:00'), null, $this->owner),
        'cancel' => fn () => $this->appointments->cancel($appointment, $this->owner),
        'complete' => fn () => $this->appointments->complete($appointment, $this->owner, 2500),
        'markNoShow' => fn () => $this->appointments->markNoShow($appointment, $this->owner),
        'undoComplete' => fn () => $this->appointments->undoComplete($appointment, $this->owner),
    };

    expect($call)->toThrow(BookingException::class);
    expect($appointment->fresh()->status)->toBe($from);
})->with([
    [AppointmentStatus::Scheduled, 'undoComplete'],
    [AppointmentStatus::Completed, 'reschedule'],
    [AppointmentStatus::Completed, 'cancel'],
    [AppointmentStatus::Completed, 'complete'],
    [AppointmentStatus::Completed, 'markNoShow'],
    [AppointmentStatus::Cancelled, 'reschedule'],
    [AppointmentStatus::Cancelled, 'cancel'],
    [AppointmentStatus::Cancelled, 'complete'],
    [AppointmentStatus::Cancelled, 'markNoShow'],
    [AppointmentStatus::Cancelled, 'undoComplete'],
    [AppointmentStatus::NoShow, 'reschedule'],
    [AppointmentStatus::NoShow, 'cancel'],
    [AppointmentStatus::NoShow, 'complete'],
    [AppointmentStatus::NoShow, 'markNoShow'],
    [AppointmentStatus::NoShow, 'undoComplete'],
]);

// ── Aanbetaling: voorbereid, staat uit (§7.8) ────────────────────────────────────────────────────────────

it('calculates a deposit only when the feature is on', function (DepositType $type, int $value, int $expected) {
    $service = Service::factory()->create(['price_cents' => 3500]);
    $service->forceFill(['deposit_type' => $type, 'deposit_value' => $value])->save();

    expect(DepositCalculator::for($service))->toBe(0);

    config(['barbercuts.features.deposits' => true]);

    expect(DepositCalculator::for($service))->toBe($expected);
})->with([
    [DepositType::None, 500, 0],
    [DepositType::Fixed, 1000, 1000],
    [DepositType::Fixed, 9900, 3500],     // nooit meer dan de prijs
    [DepositType::Percent, 20, 700],
    [DepositType::Percent, 33, 1155],
]);

it('books without a deposit while the feature is off, even if the service has one configured', function () {
    Queue::fake();
    $this->service->forceFill(['deposit_type' => DepositType::Fixed, 'deposit_value' => 1000])->save();

    $appointment = $this->appointments->book(($this->data)(), null);

    expect($appointment->deposit_cents)->toBe(0)->and($appointment->deposit_status)->toBe(DepositStatus::None);
    Queue::assertNotPushed(CancelUnpaidDeposit::class);
});

it('books with a pending deposit when the feature is on and cancels it when unpaid', function () {
    config(['barbercuts.features.deposits' => true]);
    Queue::fake([CancelUnpaidDeposit::class]);
    $this->service->forceFill(['deposit_type' => DepositType::Percent, 'deposit_value' => 20])->save();

    $appointment = $this->appointments->book(($this->data)(), null);

    expect($appointment->deposit_cents)->toBe(500)->and($appointment->deposit_status)->toBe(DepositStatus::Pending);
    Queue::assertPushed(CancelUnpaidDeposit::class, fn (CancelUnpaidDeposit $job) => $job->appointmentId === $appointment->id);

    (new CancelUnpaidDeposit($appointment->id))->handle($this->appointments);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and($appointment->fresh()->cancelled_by)->toBe(CancelledBy::System)
        ->and($appointment->fresh()->deposit_status)->toBe(DepositStatus::None);
});

it('leaves a paid deposit alone, refunds it on cancellation and forfeits it on a no-show', function () {
    config(['barbercuts.features.deposits' => true]);
    Queue::fake([CancelUnpaidDeposit::class]);
    $this->service->forceFill(['deposit_type' => DepositType::Fixed, 'deposit_value' => 1000])->save();

    $paid = fn (string $time, string $phone) => tap(
        $this->appointments->book(($this->data)(['at' => $time, 'customer' => new CustomerInput('Klant', $phone)]), null),
        fn ($appointment) => $appointment->forceFill(['deposit_status' => DepositStatus::Paid])->save(),
    );

    $cancelled = $paid('2026-11-17 10:00', '0611111111');
    $noShow = $paid('2026-11-17 12:00', '0622222222');

    (new CancelUnpaidDeposit($cancelled->id))->handle($this->appointments);
    expect($cancelled->fresh()->status)->toBe(AppointmentStatus::Scheduled);

    $this->appointments->cancel($cancelled, null);
    expect($cancelled->fresh()->deposit_status)->toBe(DepositStatus::Refunded);

    $this->travelTo(at('2026-11-17 12:20'));
    $this->appointments->markNoShow($noShow, $this->owner);
    expect($noShow->fresh()->deposit_status)->toBe(DepositStatus::Forfeited);
});
