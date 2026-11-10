<?php

namespace App\Services\Booking;

use App\Models\Barbershop;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use Illuminate\Support\Collection;

/**
 * Alles wat nodig is om de beschikbaarheid over een periode in geheugen uit te rekenen (§7.1).
 * Tijden zijn unix-seconden; een interval is [start, eind).
 *
 * @phpstan-type Interval array{0: int, 1: int}
 */
final class AvailabilityContext
{
    /**
     * @param  Collection<int, StaffMember>  $staff  kappers die de dienst doen, op id
     * @param  array<int, array<int, list<array{0: string, 1: string}>>>  $workingHours  [kapper][weekdag] => [[van, tot]]
     * @param  array<int, list<array{0: string, 1: string}>>  $openingHours  [weekdag] => [[van, tot]]
     * @param  list<array{staff: int|null, type: string, start: int, end: int}>  $timeOff
     * @param  list<array{id: int, staff: int, start: int, end: int, blockedUntil: int, duration: int}>  $appointments
     */
    public function __construct(
        public readonly Barbershop $shop,
        public readonly ShopSetting $settings,
        public readonly Collection $staff,
        public readonly array $workingHours,
        public readonly array $openingHours,
        public readonly bool $hasOpeningHours,
        public readonly array $timeOff,
        public readonly array $appointments,
        public readonly int $now,
        public readonly ?int $ignoreAppointmentId,
        public readonly bool $online,
    ) {}
}
