<?php

use App\Enums\AppointmentStatus;
use App\Enums\TimeOffType;
use App\Models\Appointment;
use App\Models\OpeningHour;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\TimeOff;
use App\Services\Booking\AvailabilityService;
use Illuminate\Support\Facades\DB;

/*
| Beschikbaarheid (docs/BOUWOPDRACHT.md §7.1 en §12). Vaste "nu": maandag 16 november 2026, 08:00 in Amsterdam.
| Dinsdag 17 november is de dag waarop de meeste tests rekenen.
*/

beforeEach(function () {
    $this->travelTo(at('2026-11-16 08:00'));

    [$this->shop] = shopWithOwner();
    $this->sam = staffWithHours($this->shop, 'Sam');
    $this->service = Service::factory()->create(['barbershop_id' => $this->shop->id, 'duration' => 30, 'buffer_after' => 0]);
    $this->availability = app(AvailabilityService::class);

    $this->slots = fn (string $date = '2026-11-17', $staff = 'sam', ?Service $service = null, ?Appointment $ignore = null, bool $online = true) => clock(
        $this->availability->slots($this->shop, $service ?? $this->service, $staff === 'sam' ? $this->sam : $staff, $date, $ignore, $online)
    );

    $this->book = fn (string $start, int $minutes = 30, $staff = null, int $buffer = 0, AppointmentStatus $status = AppointmentStatus::Scheduled) => Appointment::factory()->create([
        'barbershop_id' => $this->shop->id,
        'staff_member_id' => ($staff ?? $this->sam)->id,
        'service_id' => $this->service->id,
        'duration' => $minutes,
        'starts_at' => at($start),
        'ends_at' => at($start)->addMinutes($minutes),
        'blocked_until' => at($start)->addMinutes($minutes + $buffer),
        'status' => $status,
    ]);

    $this->settings = fn (array $values) => ShopSetting::withoutGlobalScopes()->where('barbershop_id', $this->shop->id)->update($values);
});

it('offers slots on the grid within the working hours', function () {
    $slots = ($this->slots)();

    expect($slots)->toHaveCount(35)
        ->and($slots[0])->toBe('09:00')
        ->and($slots[1])->toBe('09:15')
        ->and(end($slots))->toBe('17:30');
});

it('follows the slot interval of the shop', function () {
    ($this->settings)(['slot_interval' => 30]);

    expect(array_slice(($this->slots)(), 0, 3))->toBe(['09:00', '09:30', '10:00']);
});

it('rounds the first slot up to the grid, counted from midnight', function () {
    setWorkingHours($this->sam, [2 => [['09:10', '11:00']]]);
    ($this->settings)(['slot_interval' => 20]);

    expect(($this->slots)())->toBe(['09:20', '09:40', '10:00', '10:20']);
});

it('has no slots on a day the barber does not work', function () {
    expect(($this->slots)('2026-11-22'))->toBe([]); // zondag
});

it('keeps a break between two working blocks free', function () {
    setWorkingHours($this->sam, [2 => [['09:00', '12:30'], ['13:00', '18:00']]]);

    $slots = ($this->slots)();

    expect($slots)->toContain('12:00')->toContain('13:00')
        ->not->toContain('12:15')   // zou over de pauze heen lopen
        ->not->toContain('12:30')
        ->not->toContain('12:45');
});

it('never lets an appointment cross a block boundary', function () {
    setWorkingHours($this->sam, [2 => [['09:00', '10:00'], ['10:00', '11:00']]]);
    $long = Service::factory()->create(['barbershop_id' => $this->shop->id, 'duration' => 45]);

    // Twee aaneengesloten blokken zijn twee blokken: 09:30 + 45 min past in geen van beide.
    expect(($this->slots)(service: $long))->toBe(['09:00', '09:15', '10:00', '10:15']);
});

it('blocks time that is taken by scheduled and completed appointments', function () {
    ($this->book)('2026-11-17 10:00');
    ($this->book)('2026-11-17 14:00', status: AppointmentStatus::Completed);

    $slots = ($this->slots)();

    expect($slots)->toContain('09:30')->toContain('10:30')->toContain('13:30')->toContain('14:30')
        ->not->toContain('09:45')->not->toContain('10:00')->not->toContain('10:15')
        ->not->toContain('13:45')->not->toContain('14:00')->not->toContain('14:15');
});

it('does not block time for cancelled appointments and no-shows', function (AppointmentStatus $status) {
    ($this->book)('2026-11-17 10:00', status: $status);

    expect(($this->slots)())->toContain('10:00');
})->with([AppointmentStatus::Cancelled, AppointmentStatus::NoShow]);

it('keeps the buffer after an existing appointment free', function () {
    ($this->book)('2026-11-17 10:00', buffer: 15);

    expect(($this->slots)())->not->toContain('10:30')->toContain('10:45');
});

it('needs room for the buffer of the new appointment, except at the end of the working day', function () {
    setWorkingHours($this->sam, [2 => [['09:00', '12:00'], ['13:00', '15:00']]]);
    $service = Service::factory()->create(['barbershop_id' => $this->shop->id, 'duration' => 30, 'buffer_after' => 15]);
    ($this->book)('2026-11-17 10:00');

    $slots = ($this->slots)(service: $service);

    expect($slots)
        ->toContain('09:15')->not->toContain('09:30')   // buffer zou in de afspraak van 10:00 vallen
        ->toContain('11:15')->not->toContain('11:30')   // buffer zou in de pauze vallen
        ->toContain('14:30');                            // einde werkdag: buffer mag erover
});

it('uses the opening hours of the shop as the outer limit', function () {
    inShop($this->shop, fn () => OpeningHour::create(['day_of_week' => 2, 'opens_at' => '10:00', 'closes_at' => '16:00']));

    $slots = ($this->slots)();

    expect($slots[0])->toBe('10:00')
        ->and(end($slots))->toBe('15:30')
        // De zaak heeft openingstijden, maar niet op woensdag: dan is de zaak dicht.
        ->and(($this->slots)('2026-11-18'))->toBe([]);
});

it('closes the whole shop during shop-wide time off', function () {
    TimeOff::factory()->create([
        'barbershop_id' => $this->shop->id, 'staff_member_id' => null,
        'starts_at' => at('2026-11-17 00:00'), 'ends_at' => at('2026-11-18 00:00'),
    ]);

    expect(($this->slots)('2026-11-17'))->toBe([])
        ->and(($this->slots)('2026-11-18'))->not->toBe([]);
});

it('removes only the hours a barber is off', function () {
    TimeOff::factory()->create([
        'barbershop_id' => $this->shop->id, 'staff_member_id' => $this->sam->id,
        'starts_at' => at('2026-11-17 12:00'), 'ends_at' => at('2026-11-17 15:00'),
    ]);

    $slots = ($this->slots)();

    expect($slots)->toContain('11:30')->toContain('15:00')
        ->not->toContain('11:45')->not->toContain('12:00')->not->toContain('14:45');
});

it('does not apply the time off of one barber to another', function () {
    $mo = staffWithHours($this->shop, 'Mo');
    TimeOff::factory()->create([
        'barbershop_id' => $this->shop->id, 'staff_member_id' => $this->sam->id,
        'starts_at' => at('2026-11-17 00:00'), 'ends_at' => at('2026-11-18 00:00'),
    ]);

    expect(($this->slots)())->toBe([])
        ->and(($this->slots)(staff: $mo))->toHaveCount(35);
});

it('replaces the working hours by custom hours on that day', function () {
    TimeOff::factory()->create([
        'barbershop_id' => $this->shop->id, 'staff_member_id' => $this->sam->id, 'type' => TimeOffType::CustomHours,
        'starts_at' => at('2026-11-17 12:00'), 'ends_at' => at('2026-11-17 21:00'),
    ]);

    $slots = ($this->slots)();

    expect($slots[0])->toBe('12:00')
        ->and(end($slots))->toBe('20:30')
        // Ook op een dag waarop de kapper normaal niet werkt
        ->and(($this->slots)('2026-11-18'))->toHaveCount(35);
});

it('still clips custom hours to the opening hours of the shop', function () {
    inShop($this->shop, fn () => OpeningHour::create(['day_of_week' => 2, 'opens_at' => '09:00', 'closes_at' => '18:00']));
    TimeOff::factory()->create([
        'barbershop_id' => $this->shop->id, 'staff_member_id' => $this->sam->id, 'type' => TimeOffType::CustomHours,
        'starts_at' => at('2026-11-17 12:00'), 'ends_at' => at('2026-11-17 21:00'),
    ]);

    expect(last(($this->slots)()))->toBe('17:30');
});

it('respects the minimum notice for online bookings', function () {
    ($this->settings)(['min_notice' => 120]);

    // Nu is maandag 08:00; met 2 uur vooraf is 10:00 de eerste tijd.
    expect(($this->slots)('2026-11-16')[0])->toBe('10:00');

    $this->travelTo(at('2026-11-16 10:05'));

    expect(($this->slots)('2026-11-16')[0])->toBe('12:15');
});

it('has no slots in the past', function () {
    $this->travelTo(at('2026-11-17 19:00'));

    expect(($this->slots)('2026-11-17'))->toBe([])
        ->and(($this->slots)('2026-11-16'))->toBe([]);
});

it('respects the maximum number of days ahead', function () {
    ($this->settings)(['max_days_ahead' => 7]);

    expect(($this->slots)('2026-11-23'))->not->toBe([])   // vandaag + 7
        ->and(($this->slots)('2026-11-24'))->toBe([]);     // vandaag + 8
});

it('lets staff book without notice and beyond the maximum days', function () {
    ($this->settings)(['min_notice' => 1440, 'max_days_ahead' => 7]);

    expect(($this->slots)('2026-11-16', online: false)[0])->toBe('09:00')
        ->and(($this->slots)('2026-12-15', online: false))->not->toBe([]);
});

it('ignores the appointment that is being rescheduled', function () {
    $appointment = ($this->book)('2026-11-17 10:00');

    expect(($this->slots)())->not->toContain('10:00')
        ->and(($this->slots)(ignore: $appointment))->toContain('10:00')->toContain('10:15');
});

it('only offers barbers who do the service', function () {
    $mo = staffWithHours($this->shop, 'Mo', [2 => [['12:00', '14:00']]]);
    $beard = Service::factory()->create(['barbershop_id' => $this->shop->id, 'duration' => 30]);
    DB::table('service_staff_member')->insert(['service_id' => $beard->id, 'staff_member_id' => $mo->id]);

    expect(($this->slots)(staff: null, service: $beard))->toBe(['12:00', '12:15', '12:30', '12:45', '13:00', '13:15', '13:30'])
        // Sam doet deze dienst niet
        ->and(($this->slots)(service: $beard))->toBe([])
        // Geen koppeling = iedereen
        ->and(($this->slots)(staff: null))->toHaveCount(35);
});

it('skips inactive barbers', function () {
    $this->sam->update(['is_active' => false]);

    expect(($this->slots)(staff: null))->toBe([]);
});

it('gives the union of all barbers for no preference', function () {
    setWorkingHours($this->sam, [2 => [['09:00', '10:00']]]);
    staffWithHours($this->shop, 'Mo', [2 => [['09:30', '11:00']]]);
    ($this->book)('2026-11-17 09:00');

    expect(($this->slots)(staff: null))->toBe(['09:30', '09:45', '10:00', '10:15', '10:30']);
});

it('assigns no preference to the barber with the fewest booked minutes, then the lowest sort order', function () {
    $mo = staffWithHours($this->shop, 'Mo', sortOrder: 1);
    $lars = staffWithHours($this->shop, 'Lars', sortOrder: 2);
    $names = fn (string $time) => array_map(fn ($staff) => $staff->name, $this->availability->freeStaffAt($this->shop, $this->service, null, at($time)));

    // Niemand geboekt: laagste sort_order eerst
    expect($names('2026-11-17 15:00'))->toBe(['Sam', 'Mo', 'Lars']);

    ($this->book)('2026-11-17 09:00', 60);              // Sam: 60 min
    ($this->book)('2026-11-17 09:00', 30, $mo);         // Mo: 30 min
    ($this->book)('2026-11-18 09:00', 240, $lars);      // Lars: andere dag telt niet

    expect($names('2026-11-17 15:00'))->toBe(['Lars', 'Mo', 'Sam'])
        // Wie bezet is valt af
        ->and($names('2026-11-17 09:00'))->toBe(['Lars']);
});

it('handles the start of summer time', function () {
    // Zondag 28 maart 2027: de klok gaat van 02:00 naar 03:00
    $this->travelTo(at('2027-03-25 08:00'));
    setWorkingHours($this->sam, [6 => [['09:00', '12:00']], 7 => [['09:00', '12:00']]]);

    $saturday = $this->availability->slots($this->shop, $this->service, $this->sam, '2027-03-27');
    $sunday = $this->availability->slots($this->shop, $this->service, $this->sam, '2027-03-28');

    expect(clock($saturday))->toBe(clock($sunday))
        ->and($saturday[0]->toDateTimeString())->toBe('2027-03-27 08:00:00')   // UTC+1
        ->and($sunday[0]->toDateTimeString())->toBe('2027-03-28 07:00:00');    // UTC+2
});

it('handles the end of summer time', function () {
    // Zondag 31 oktober 2027: de klok gaat van 03:00 naar 02:00
    $this->travelTo(at('2027-10-28 08:00'));
    setWorkingHours($this->sam, [6 => [['09:00', '12:00']], 7 => [['09:00', '12:00']]]);

    $saturday = $this->availability->slots($this->shop, $this->service, $this->sam, '2027-10-30');
    $sunday = $this->availability->slots($this->shop, $this->service, $this->sam, '2027-10-31');

    expect(clock($saturday))->toBe(clock($sunday))->toHaveCount(11)
        ->and($saturday[0]->toDateTimeString())->toBe('2027-10-30 07:00:00')   // UTC+2
        ->and($sunday[0]->toDateTimeString())->toBe('2027-10-31 08:00:00');    // UTC+1
});

it('tells per day whether there is availability', function () {
    TimeOff::factory()->create([
        'barbershop_id' => $this->shop->id, 'staff_member_id' => null,
        'starts_at' => at('2026-11-18 00:00'), 'ends_at' => at('2026-11-19 00:00'),
    ]);

    expect($this->availability->availableDays($this->shop, $this->service, null, '2026-11-16', '2026-11-22'))->toBe([
        '2026-11-16' => true,
        '2026-11-17' => true,
        '2026-11-18' => false,   // gesloten
        '2026-11-19' => true,
        '2026-11-20' => true,
        '2026-11-21' => true,
        '2026-11-22' => false,   // zondag
    ]);
});

it('loads a whole period with a fixed number of queries', function () {
    staffWithHours($this->shop, 'Mo');
    ($this->book)('2026-11-17 10:00');

    $count = function (string $to): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->availability->availableDays($this->shop, $this->service, null, '2026-11-16', $to);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($count('2026-11-17'))->toBe($count('2026-12-15'))->toBeLessThanOrEqual(7);
});

it('never counts appointments or time off of another shop', function () {
    [$other] = shopWithOwner();
    $otherStaff = staffWithHours($other, 'Buurman');
    Appointment::factory()->create(['barbershop_id' => $other->id, 'staff_member_id' => $otherStaff->id, 'starts_at' => at('2026-11-17 10:00'), 'ends_at' => at('2026-11-17 10:30'), 'blocked_until' => at('2026-11-17 10:30')]);
    TimeOff::factory()->create(['barbershop_id' => $other->id, 'starts_at' => at('2026-11-17 00:00'), 'ends_at' => at('2026-11-18 00:00')]);

    expect(($this->slots)())->toHaveCount(35);
});
