<?php

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Livewire\App\Appointments\Form as AppointmentForm;
use App\Livewire\App\Calendar;
use App\Livewire\App\Customers\Index as CustomerIndex;
use App\Livewire\App\Customers\Show as CustomerShow;
use App\Livewire\App\Today;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\StaffAppointmentUpdate;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\BookingData;
use App\Services\Booking\CustomerInput;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
| De kapper-app (docs/BOUWOPDRACHT.md §9.1). "Nu" is dinsdag 17 november 2026, 10:10.
| Sam is kapper met een account; Mo heeft geen account.
*/

beforeEach(function () {
    Notification::fake();
    $this->travelTo(at('2026-11-17 10:10'));

    [$this->shop, $this->owner] = shopWithOwner(['name' => 'JF Cuts']);
    [$this->samUser, $this->sam] = barberIn($this->shop, 'Sam');
    setWorkingHours($this->sam, [2 => [['09:00', '12:30'], ['13:00', '18:00']], 3 => [['09:00', '18:00']]]);
    $this->mo = staffWithHours($this->shop, 'Mo', sortOrder: 1);
    $this->service = Service::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Knippen', 'duration' => 30, 'price_cents' => 2500]);
    $this->daan = Customer::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Daan de Vries', 'phone' => '+31612345678']);

    $this->appointment = fn (string $start, $staff = null, ?Customer $customer = null, AppointmentStatus $status = AppointmentStatus::Scheduled) => Appointment::factory()->create([
        'barbershop_id' => $this->shop->id,
        'staff_member_id' => ($staff ?? $this->sam)->id,
        'service_id' => $this->service->id,
        'customer_id' => ($customer ?? $this->daan)->id,
        'service_name' => 'Knippen',
        'starts_at' => at($start),
        'ends_at' => at($start)->addMinutes(30),
        'blocked_until' => at($start)->addMinutes(30),
        'status' => $status,
    ]);
});

// ── Vandaag ──────────────────────────────────────────────────────────────────────────────────────────────

it('shows the owner the day of every barber with the next appointment highlighted', function () {
    ($this->appointment)('2026-11-17 09:00');
    ($this->appointment)('2026-11-17 11:00', $this->mo, Customer::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Yusuf Kaya']));
    ($this->appointment)('2026-11-17 15:00', status: AppointmentStatus::Cancelled, customer: Customer::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Afgezegd']));
    ($this->appointment)('2026-11-18 09:00', customer: Customer::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Morgen Pas']));

    $this->actingAs($this->owner)->get('/app')->assertOk()
        ->assertSeeInOrder(['Eerstvolgende', 'Yusuf Kaya', 'Vandaag', 'Daan de Vries', 'Yusuf Kaya'])
        ->assertDontSee('Afgezegd')
        ->assertDontSee('Morgen Pas');
});

it('shows a barber only the own day', function () {
    ($this->appointment)('2026-11-17 11:00');
    ($this->appointment)('2026-11-17 11:00', $this->mo, Customer::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Klant van Mo']));

    $this->actingAs($this->samUser)->get('/app')->assertOk()->assertSee('Daan de Vries')->assertDontSee('Klant van Mo');
});

// ── Voltooien en andere acties ───────────────────────────────────────────────────────────────────────────

it('completes an appointment in three taps: card, complete, payment method', function () {
    $appointment = ($this->appointment)('2026-11-17 10:00');
    actingInShop($this->samUser, $this->shop);

    Livewire::test(Today::class)
        ->call('selectAppointment', $appointment->id)          // tik 1: de kaart
        ->assertDispatched('open-sheet')
        ->assertSet('amount', '25,00')                          // voorgevuld met de prijs
        ->call('showSheet', 'complete')                         // tik 2: Voltooien
        ->assertSee('Betaald met')
        ->call('completeAppointment', 'pin')                    // tik 3: Pin slaat op
        ->assertDispatched('close-sheet');

    $fresh = $appointment->fresh();

    expect($fresh->status)->toBe(AppointmentStatus::Completed)
        ->and($fresh->amount_paid_cents)->toBe(2500)
        ->and($fresh->tip_cents)->toBe(0)
        ->and($fresh->payment_method)->toBe(PaymentMethod::Pin);
});

it('records another amount, a tip and the payment method', function () {
    $appointment = ($this->appointment)('2026-11-17 10:00');
    actingInShop($this->owner, $this->shop);

    Livewire::test(Today::class)
        ->call('selectAppointment', $appointment->id)
        ->set('amount', '30')
        ->call('setTip', '5')
        ->call('completeAppointment', 'cash');

    expect($appointment->fresh()->only(['amount_paid_cents', 'tip_cents']))->toBe(['amount_paid_cents' => 3000, 'tip_cents' => 500])
        ->and($appointment->fresh()->payment_method)->toBe(PaymentMethod::Cash);

    $second = ($this->appointment)('2026-11-17 09:00');

    Livewire::test(Today::class)
        ->call('selectAppointment', $second->id)
        ->call('setTip', 'custom')->set('tip', '3,50')
        ->call('completeAppointment', 'tikkie');

    expect($second->fresh()->tip_cents)->toBe(350);
});

it('rejects an invalid amount when completing', function () {
    $appointment = ($this->appointment)('2026-11-17 10:00');
    actingInShop($this->owner, $this->shop);

    Livewire::test(Today::class)
        ->call('selectAppointment', $appointment->id)
        ->set('amount', 'veel')
        ->call('completeAppointment', 'pin')
        ->assertHasErrors('amount');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('does not offer completing before the appointment has started', function () {
    $appointment = ($this->appointment)('2026-11-17 16:00');
    actingInShop($this->owner, $this->shop);

    Livewire::test(Today::class)
        ->call('selectAppointment', $appointment->id)
        ->assertDontSee('Voltooien')->assertDontSee('No-show')
        ->assertSee('Verplaatsen')->assertSee('Annuleren')->assertSee('Bellen')->assertSee('WhatsApp')
        ->call('completeAppointment', 'pin');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('marks a no-show, cancels and undoes a completion from the sheet', function () {
    $noShow = ($this->appointment)('2026-11-17 09:00');
    $cancel = ($this->appointment)('2026-11-17 15:00');
    $undo = ($this->appointment)('2026-11-17 09:30');
    actingInShop($this->owner, $this->shop);

    Livewire::test(Today::class)
        ->call('selectAppointment', $noShow->id)->call('markNoShow')
        ->call('selectAppointment', $cancel->id)->call('showSheet', 'cancel')->call('cancelAppointment')
        ->call('selectAppointment', $undo->id)->call('completeAppointment', 'pin')
        ->call('selectAppointment', $undo->id)->assertSee('Terugzetten naar gepland')->call('undoComplete');

    expect($noShow->fresh()->status)->toBe(AppointmentStatus::NoShow)
        ->and($this->daan->fresh()->no_show_count)->toBe(1)
        ->and($cancel->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and($undo->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('lets a barber look at a colleague\'s appointment but not change it', function () {
    $appointment = ($this->appointment)('2026-11-17 10:00', $this->mo);
    actingInShop($this->samUser, $this->shop);

    Livewire::withQueryParams(['kapper' => 'all'])->test(Calendar::class)
        ->call('selectAppointment', $appointment->id)
        ->assertSee('agenda van Mo')
        ->assertDontSee('Voltooien')
        ->call('completeAppointment', 'pin')
        ->assertForbidden();

    Livewire::withQueryParams(['kapper' => 'all'])->test(Calendar::class)->call('selectAppointment', $appointment->id)->call('cancelAppointment')->assertForbidden();
    Livewire::withQueryParams(['kapper' => 'all'])->test(Calendar::class)->call('selectAppointment', $appointment->id)->call('markNoShow')->assertForbidden();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('cannot select or act on an appointment of another shop', function () {
    [$other] = shopWithOwner();
    $foreign = Appointment::factory()->create(['barbershop_id' => $other->id, 'starts_at' => at('2026-11-17 10:00'), 'ends_at' => at('2026-11-17 10:30'), 'blocked_until' => at('2026-11-17 10:30')]);
    actingInShop($this->owner, $this->shop);

    Livewire::test(Today::class)->call('selectAppointment', $foreign->id)->assertNotFound();
    Livewire::test(Today::class)->set('selectedId', $foreign->id)->set('amount', '25')->call('completeAppointment', 'pin')->assertNotFound();
    Livewire::test(Today::class)->set('selectedId', $foreign->id)->call('cancelAppointment')->assertNotFound();

    expect($foreign->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

// ── Agenda ───────────────────────────────────────────────────────────────────────────────────────────────

it('opens the calendar on the own agenda for a barber and on everyone for an owner without agenda', function () {
    actingInShop($this->samUser, $this->shop);
    Livewire::test(Calendar::class)->assertSet('staffFilter', (string) $this->sam->id)->assertSet('date', '2026-11-17');

    actingInShop($this->owner, $this->shop);
    Livewire::test(Calendar::class)->assertSet('staffFilter', 'all')->assertSee('Sam')->assertSee('Mo');
});

it('shows the appointments of the chosen barber and day', function () {
    ($this->appointment)('2026-11-17 11:00');
    ($this->appointment)('2026-11-17 14:00', $this->mo, Customer::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Klant van Mo']));
    ($this->appointment)('2026-11-18 11:00', customer: Customer::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Woensdag Klant']));
    ($this->appointment)('2026-11-17 15:00', status: AppointmentStatus::Cancelled, customer: Customer::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Afgezegd']));
    actingInShop($this->samUser, $this->shop);

    Livewire::test(Calendar::class)
        ->assertSee('11:00 Daan de Vries')
        ->assertDontSee('Klant van Mo')->assertDontSee('Woensdag Klant')->assertDontSee('Afgezegd')
        ->set('staffFilter', 'all')
        ->assertSee('Klant van Mo')
        ->set('date', '2026-11-18')
        ->assertSee('Woensdag Klant')->assertDontSee('11:00 Daan de Vries')
        ->call('shiftWeek', 1)
        ->assertSet('date', '2026-11-25')
        ->call('goToToday')
        ->assertSet('date', '2026-11-17');
});

it('starts a new appointment at the tapped time with that barber', function () {
    actingInShop($this->owner, $this->shop);

    Livewire::test(Calendar::class)
        ->call('newAt', $this->mo->id, 14 * 60 + 30)
        ->assertRedirect(route('app.appointments.create', ['kapper' => $this->mo->id, 'dag' => '2026-11-17', 'tijd' => '14:30']));

    [$other] = shopWithOwner();
    Livewire::test(Calendar::class)->call('newAt', staffWithHours($other)->id, 600)->assertNotFound();
});

it('falls back to a valid day and barber when the url is tampered with', function () {
    actingInShop($this->owner, $this->shop);

    Livewire::withQueryParams(['dag' => 'gisteren', 'kapper' => '999999'])->test(Calendar::class)
        ->assertSet('date', '2026-11-17')
        ->assertSet('staffFilter', 'all');
});

// ── Nieuwe afspraak ──────────────────────────────────────────────────────────────────────────────────────

it('books by phone for an existing customer: search, pick, time, save', function () {
    actingInShop($this->samUser, $this->shop);

    Livewire::test(AppointmentForm::class)
        ->assertSet('staffId', $this->sam->id)                 // voorgeselecteerd: jezelf
        ->assertSet('serviceId', $this->service->id)
        ->assertSet('date', '2026-11-17')
        ->set('search', 'daan')
        ->assertSee('Daan de Vries')
        ->call('chooseCustomer', $this->daan->id)
        ->assertSeeInOrder(['10:15', '10:30'])                 // de eerstvolgende vrije tijden, zonder minimale boektermijn
        ->assertDontSee('12:30')                               // pauze
        ->set('time', '10:30')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('app.calendar', ['dag' => '2026-11-17', 'kapper' => $this->sam->id]));

    $appointment = Appointment::withoutGlobalScopes()->firstOrFail();

    expect($appointment->customer_id)->toBe($this->daan->id)
        ->and($appointment->staff_member_id)->toBe($this->sam->id)
        ->and($appointment->source)->toBe(AppointmentSource::Staff)
        ->and($appointment->starts_at->toDateTimeString())->toBe('2026-11-17 09:30:00')
        ->and($appointment->created_by_user_id)->toBe($this->samUser->id);
});

it('finds customers by a part of the phone number and only in the own shop', function () {
    [$other] = shopWithOwner();
    Customer::factory()->create(['barbershop_id' => $other->id, 'name' => 'Daan van de Buren', 'phone' => '+31612345699']);
    actingInShop($this->owner, $this->shop);

    Livewire::test(AppointmentForm::class)
        ->set('search', '0612345')
        ->assertSee('Daan de Vries')->assertDontSee('Daan van de Buren')
        ->call('chooseCustomer', Customer::withoutGlobalScopes()->where('name', 'Daan van de Buren')->value('id'))
        ->assertNotFound();
});

it('adds a new customer with name and phone while booking', function () {
    actingInShop($this->owner, $this->shop);

    Livewire::test(AppointmentForm::class)
        ->set('search', 'Yusuf Kaya')
        ->call('startNewCustomer')
        ->assertSet('name', 'Yusuf Kaya')
        ->set('phone', '06 87654321')
        ->set('staffId', $this->mo->id)
        ->set('time', '11:00')
        ->call('save')
        ->assertHasNoErrors();

    $customer = Customer::withoutGlobalScopes()->where('phone', '+31687654321')->firstOrFail();

    expect($customer->name)->toBe('Yusuf Kaya')
        ->and($customer->user_id)->toBeNull()
        ->and(Appointment::withoutGlobalScopes()->firstOrFail()->staff_member_id)->toBe($this->mo->id);
});

it('requires a customer and a free time', function () {
    ($this->appointment)('2026-11-17 14:00');
    actingInShop($this->owner, $this->shop);

    Livewire::test(AppointmentForm::class)->set('staffId', $this->sam->id)->set('time', '11:00')->call('save')->assertHasErrors('customer');
    Livewire::test(AppointmentForm::class)->call('chooseCustomer', $this->daan->id)->call('save')->assertHasErrors('time');
    Livewire::test(AppointmentForm::class)->call('chooseCustomer', $this->daan->id)->set('staffId', $this->sam->id)->set('time', '14:00')->call('save')->assertHasErrors('time');
    Livewire::test(AppointmentForm::class)->call('startNewCustomer')->set('name', 'Yusuf')->set('phone', '123')->set('time', '11:00')->call('save')->assertHasErrors('time');

    expect(Appointment::withoutGlobalScopes()->count())->toBe(1);
});

it('lets a barber book only in the own agenda', function () {
    actingInShop($this->samUser, $this->shop);

    Livewire::withQueryParams(['kapper' => $this->mo->id])->test(AppointmentForm::class)
        ->assertSet('staffId', $this->sam->id)
        ->set('staffId', $this->mo->id)
        ->call('chooseCustomer', $this->daan->id)
        ->set('time', '11:00')
        ->call('save')
        ->assertHasErrors('staffId');

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

it('prefills barber, day and time from the calendar', function () {
    actingInShop($this->owner, $this->shop);

    Livewire::withQueryParams(['kapper' => $this->mo->id, 'dag' => '2026-11-18', 'tijd' => '14:30'])->test(AppointmentForm::class)
        ->assertSet('staffId', $this->mo->id)
        ->assertSet('date', '2026-11-18')
        ->assertSet('time', '14:30');
});

it('books a walk-in that starts now', function () {
    actingInShop($this->samUser, $this->shop);

    Livewire::test(AppointmentForm::class)->call('chooseCustomer', $this->daan->id)->call('walkIn')->assertHasNoErrors();

    $appointment = Appointment::withoutGlobalScopes()->firstOrFail();

    expect($appointment->source)->toBe(AppointmentSource::WalkIn)
        ->and($appointment->starts_at->toDateTimeString())->toBe('2026-11-17 09:10:00');
});

it('reschedules an appointment from the app, the owner also to another barber', function () {
    $appointment = ($this->appointment)('2026-11-17 14:00');
    actingInShop($this->owner, $this->shop);

    Livewire::test(AppointmentForm::class, ['appointment' => $appointment->id])
        ->assertSee('Afspraak verplaatsen')->assertSee('Daan de Vries')
        ->assertSet('staffId', $this->sam->id)
        ->set('staffId', $this->mo->id)
        ->set('date', '2026-11-18')
        ->set('time', '09:00')
        ->call('save')
        ->assertHasNoErrors();

    expect($appointment->fresh()->staff_member_id)->toBe($this->mo->id)
        ->and($appointment->fresh()->starts_at->toDateTimeString())->toBe('2026-11-18 08:00:00')
        ->and(Appointment::withoutGlobalScopes()->count())->toBe(1);
});

it('does not open the reschedule screen for a colleague\'s, finished or foreign appointment', function () {
    $ofMo = ($this->appointment)('2026-11-17 14:00', $this->mo);
    $completed = ($this->appointment)('2026-11-17 09:00', status: AppointmentStatus::Completed);
    $foreign = Appointment::factory()->create();

    $this->actingAs($this->samUser)->get("/app/afspraken/{$ofMo->id}/verplaatsen")->assertForbidden();
    $this->actingAs($this->samUser)->get("/app/afspraken/{$completed->id}/verplaatsen")->assertNotFound();
    $this->actingAs($this->samUser)->get("/app/afspraken/{$foreign->id}/verplaatsen")->assertNotFound();
});

// ── Klanten ──────────────────────────────────────────────────────────────────────────────────────────────

it('lists and searches the customers of the shop', function () {
    Customer::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Yusuf Kaya', 'phone' => '+31687654321', 'last_visit_at' => at('2026-11-16 10:00')]);
    Customer::factory()->create(['name' => 'Klant van de Buren']);
    actingInShop($this->samUser, $this->shop);

    Livewire::test(CustomerIndex::class)
        ->assertSee('2 klanten')
        ->assertSeeInOrder(['Daan de Vries', 'Nog niet geweest', 'Yusuf Kaya', 'Laatste bezoek gisteren'])
        ->assertDontSee('Klant van de Buren')
        ->set('search', 'yus')->assertSee('Yusuf Kaya')->assertDontSee('Daan de Vries')
        ->set('search', '0687')->assertSee('Yusuf Kaya')->assertDontSee('Daan de Vries')
        ->set('search', 'niemand')->assertSee('Niemand gevonden');
});

it('shows the customer with history and lets a barber save notes and details', function () {
    ($this->appointment)('2026-11-10 10:00', status: AppointmentStatus::Completed);
    ($this->appointment)('2026-11-03 10:00', $this->mo, status: AppointmentStatus::NoShow);
    actingInShop($this->samUser, $this->shop);

    Livewire::test(CustomerShow::class, ['customer' => $this->daan->id])
        ->assertSee('Daan de Vries')->assertSee('+31612345678')
        ->assertSeeInOrder(['Bezoeken', 'di 10 nov 2026', 'Voltooid', 'di 3 nov 2026', 'No-show'])
        ->set('notes', 'Fade 1,5 aan de zijkant')
        ->call('saveNotes')
        ->set('name', 'Daan J. de Vries')->set('phone', '06 11112222')->set('email', 'Daan@Example.com')
        ->call('saveDetails')
        ->assertHasNoErrors();

    expect($this->daan->fresh()->only(['name', 'phone', 'email', 'notes']))->toBe([
        'name' => 'Daan J. de Vries', 'phone' => '+31611112222', 'email' => 'daan@example.com', 'notes' => 'Fade 1,5 aan de zijkant',
    ]);
});

it('does not give two customers the same phone number', function () {
    Customer::factory()->create(['barbershop_id' => $this->shop->id, 'phone' => '+31687654321']);
    actingInShop($this->owner, $this->shop);

    Livewire::test(CustomerShow::class, ['customer' => $this->daan->id])->set('phone', '0687654321')->call('saveDetails')->assertHasErrors('phone');

    expect($this->daan->fresh()->phone)->toBe('+31612345678');
});

it('gives a 404 for a customer of another shop', function () {
    $foreign = Customer::factory()->create();

    $this->actingAs($this->owner)->get("/app/klanten/{$foreign->id}")->assertNotFound();
    $this->actingAs($this->owner)->get("/app/klanten/{$this->daan->id}")->assertOk();
});

// ── Berichten voor de kapper ─────────────────────────────────────────────────────────────────────────────

it('notifies the barber of an online booking, and the owner when the barber has no account', function () {
    $book = fn ($staff, string $time, string $phone) => app(AppointmentService::class)->book(
        new BookingData($this->shop, $this->service, $staff, at($time), new CustomerInput('Klant', $phone)), null,
    );

    $forSam = $book($this->sam, '2026-11-17 15:00', '0611111111');
    $forMo = $book($this->mo, '2026-11-17 15:00', '0622222222');

    Notification::assertSentTo($this->samUser, StaffAppointmentUpdate::class, function (StaffAppointmentUpdate $notification) use ($forSam) {
        $mail = $notification->toMail($this->samUser);

        return $notification->appointmentId === $forSam->id
            && $mail->subject === 'Nieuwe afspraak: Klant, Vandaag 15:00'
            && str_contains($mail->actionUrl, '/app/agenda');
    });
    Notification::assertSentTo($this->owner, StaffAppointmentUpdate::class, fn (StaffAppointmentUpdate $notification) => $notification->appointmentId === $forMo->id);
    Notification::assertSentToTimes($this->owner, StaffAppointmentUpdate::class, 1);
    Notification::assertNotSentTo($this->samUser, AppointmentConfirmed::class);
});

it('notifies the barber when a customer cancels or reschedules, not when staff does', function () {
    $appointments = app(AppointmentService::class);
    $byCustomer = $appointments->book(new BookingData($this->shop, $this->service, $this->sam, at('2026-11-18 10:00'), new CustomerInput('Klant', '0611111111')), null);
    $byStaff = $appointments->book(new BookingData($this->shop, $this->service, $this->sam, at('2026-11-18 14:00'), $this->daan, AppointmentSource::Staff), $this->owner);

    $appointments->reschedule($byCustomer, at('2026-11-18 11:00'), null, null);
    $appointments->cancel($byCustomer, null);
    $appointments->reschedule($byStaff, at('2026-11-18 15:00'), null, $this->owner);
    $appointments->cancel($byStaff, $this->owner);

    $types = Notification::sent($this->samUser, StaffAppointmentUpdate::class)->map(fn (StaffAppointmentUpdate $notification) => [$notification->appointmentId, $notification->type])->all();

    expect($types)->toBe([
        [$byCustomer->id, 'booked'],
        [$byCustomer->id, 'rescheduled'],
        [$byCustomer->id, 'cancelled'],
    ]);
});

// ── PWA ──────────────────────────────────────────────────────────────────────────────────────────────────

it('ships a manifest, icons and a service worker that never caches pages', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/app')
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192')->toContain('512x512');

    foreach ($manifest['icons'] as $icon) {
        [$width, $height] = getimagesize(public_path($icon['src']));
        expect("{$width}x{$height}")->toBe($icon['sizes']);
    }

    $worker = (string) file_get_contents(public_path('sw.js'));

    expect($worker)->toContain("request.mode === 'navigate'")->toContain('/offline.html')
        ->and(file_exists(public_path('offline.html')))->toBeTrue();

    $this->get('/')->assertSee('rel="manifest"', false)->assertSee("serviceWorker.register('/sw.js')", false);
});
