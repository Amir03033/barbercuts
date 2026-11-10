<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\TimeOffType;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\OpeningHour;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Beschikbaarheid per kapper (docs/BOUWOPDRACHT.md §7.1).
 *
 * Werkt met tijdsintervallen in de tijdzone van de zaak en laadt alles voor een periode in een vast aantal
 * queries. Filtert altijd expliciet op de zaak, zodat het ook buiten een tenant-request werkt (/afspraak/{token}).
 *
 * @phpstan-type Interval array{0: int, 1: int}
 */
class AvailabilityService
{
    /**
     * Vrije starttijden voor een dienst op een dag. $staff = null betekent "geen voorkeur": de unie over alle kappers.
     *
     * @param  bool  $online  true: min_notice en max_days_ahead gelden (klant). false: alles vanaf nu (kapper).
     * @return list<CarbonImmutable> in UTC, oplopend
     */
    public function slots(Barbershop $shop, Service $service, ?StaffMember $staff, string $date, ?Appointment $ignore = null, bool $online = true): array
    {
        $context = $this->context($shop, $service, $staff, $date, $date, $ignore, $online);

        return array_map(
            fn (int $timestamp): CarbonImmutable => CarbonImmutable::createFromTimestampUTC($timestamp),
            $this->slotTimestamps($context, $service, $date),
        );
    }

    /**
     * Per dag in de periode: zijn er nog vrije tijden? Voor de datumstrip in de boekflow.
     *
     * @return array<string, bool> 'Y-m-d' => beschikbaar
     */
    public function availableDays(Barbershop $shop, Service $service, ?StaffMember $staff, string $from, string $to, ?Appointment $ignore = null, bool $online = true): array
    {
        $context = $this->context($shop, $service, $staff, $from, $to, $ignore, $online);
        $timezone = $shop->timezone;
        $days = [];

        for ($day = CarbonImmutable::parse($from, $timezone); $day->toDateString() <= $to; $day = $day->addDay()) {
            $date = $day->toDateString();
            $days[$date] = $this->slotTimestamps($context, $service, $date) !== [];
        }

        return $days;
    }

    /**
     * De kappers die op dit tijdstip vrij zijn voor deze dienst, in volgorde van toewijzen (§7.2):
     * minste geboekte minuten die dag, bij gelijkspel de laagste sort_order.
     *
     * @return list<StaffMember>
     */
    public function freeStaffAt(Barbershop $shop, Service $service, ?StaffMember $staff, CarbonImmutable $startsAt, ?Appointment $ignore = null, bool $online = true): array
    {
        $date = $startsAt->setTimezone($shop->timezone)->toDateString();
        $context = $this->context($shop, $service, $staff, $date, $date, $ignore, $online);
        $start = $startsAt->getTimestamp();

        [$dayStart, $dayEnd] = $this->dayBounds($context, $date);
        $free = [];

        foreach ($context->staff as $candidate) {
            $fits = $online
                ? in_array($start, $this->staffSlots($context, $service, $candidate->id, $date), true)
                : $start >= $context->now - 60 && $this->fits($context, $service, $candidate->id, $date, $start);

            if (! $fits) {
                continue;
            }

            $bookedMinutes = 0;

            foreach ($context->appointments as $appointment) {
                if ($appointment['staff'] === $candidate->id && $appointment['start'] >= $dayStart && $appointment['start'] < $dayEnd) {
                    $bookedMinutes += $appointment['duration'];
                }
            }

            $free[] = ['staff' => $candidate, 'minutes' => $bookedMinutes];
        }

        usort($free, fn (array $a, array $b): int => [$a['minutes'], $a['staff']->sort_order, $a['staff']->id] <=> [$b['minutes'], $b['staff']->sort_order, $b['staff']->id]);

        return array_column($free, 'staff');
    }

    /**
     * Werkblokken per kapper op één dag, voor de agenda (pauzes en vrije tijd vallen ertussen).
     *
     * @param  Collection<int, StaffMember>  $staff  op id
     * @return array<int, list<array{0: CarbonImmutable, 1: CarbonImmutable}>> per kapper: [[van, tot]] in UTC
     */
    public function workBlocksForDay(Barbershop $shop, Collection $staff, string $date): array
    {
        $context = $this->buildContext($shop, $staff->keyBy('id'), $date, $date, null, false);
        $blocks = [];

        foreach ($context->staff as $member) {
            $blocks[$member->id] = array_map(
                fn (array $block): array => [CarbonImmutable::createFromTimestampUTC($block[0]), CarbonImmutable::createFromTimestampUTC($block[1])],
                $this->workBlocks($context, $member->id, $date),
            );
        }

        return $blocks;
    }

    /**
     * Beschikbare werkminuten van deze kappers in een periode, voor de bezettingsgraad (§7.5).
     *
     * @param  Collection<int, StaffMember>  $staff  op id
     */
    public function workMinutes(Barbershop $shop, Collection $staff, string $from, string $to): int
    {
        $context = $this->buildContext($shop, $staff->keyBy('id'), $from, $to, null, false);
        $seconds = 0;

        for ($day = CarbonImmutable::parse($from, $shop->timezone); $day->toDateString() <= $to; $day = $day->addDay()) {
            foreach ($context->staff as $member) {
                foreach ($this->workBlocks($context, $member->id, $day->toDateString()) as [$start, $end]) {
                    $seconds += $end - $start;
                }
            }
        }

        return intdiv($seconds, 60);
    }

    /**
     * De actieve kappers die deze dienst doen. Geen koppeling voor de dienst = iedereen.
     *
     * @return Collection<int, StaffMember>
     */
    public function eligibleStaff(Barbershop $shop, Service $service): Collection
    {
        $linked = DB::table('service_staff_member')->where('service_id', $service->id)->pluck('staff_member_id')->all();

        return StaffMember::withoutGlobalScopes()
            ->where('barbershop_id', $shop->id)
            ->where('is_active', true)
            ->when($linked !== [], fn ($query) => $query->whereIn('id', $linked))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->keyBy('id');
    }

    /**
     * Laad werktijden, openingstijden, time_off en afspraken voor de hele periode in één keer.
     */
    private function context(Barbershop $shop, Service $service, ?StaffMember $staff, string $from, string $to, ?Appointment $ignore, bool $online): AvailabilityContext
    {
        $eligible = $this->eligibleStaff($shop, $service);

        if ($staff !== null) {
            $eligible = $eligible->filter(fn (StaffMember $candidate): bool => $candidate->id === $staff->id);
        }

        return $this->buildContext($shop, $eligible, $from, $to, $ignore, $online);
    }

    /**
     * @param  Collection<int, StaffMember>  $eligible  kappers, op id
     */
    private function buildContext(Barbershop $shop, Collection $eligible, string $from, string $to, ?Appointment $ignore, bool $online): AvailabilityContext
    {
        $staffIds = $eligible->keys()->all();
        $timezone = $shop->timezone;
        $rangeStart = CarbonImmutable::parse($from, $timezone)->startOfDay()->utc();
        $rangeEnd = CarbonImmutable::parse($to, $timezone)->addDay()->startOfDay()->utc();

        $workingHours = [];

        foreach (WorkingHour::query()->whereIn('staff_member_id', $staffIds)->orderBy('starts_at')->get() as $row) {
            $workingHours[$row->staff_member_id][$row->day_of_week][] = [substr((string) $row->starts_at, 0, 5), substr((string) $row->ends_at, 0, 5)];
        }

        $openingHours = [];
        $openingRows = OpeningHour::withoutGlobalScopes()->where('barbershop_id', $shop->id)->orderBy('opens_at')->get();

        foreach ($openingRows as $row) {
            $openingHours[$row->day_of_week][] = [substr((string) $row->opens_at, 0, 5), substr((string) $row->closes_at, 0, 5)];
        }

        $timeOff = TimeOff::withoutGlobalScopes()
            ->where('barbershop_id', $shop->id)
            ->where('starts_at', '<', $rangeEnd)
            ->where('ends_at', '>', $rangeStart)
            ->where(fn ($query) => $query->whereNull('staff_member_id')->orWhereIn('staff_member_id', $staffIds))
            ->get()
            ->map(fn (TimeOff $off): array => [
                'staff' => $off->staff_member_id,
                'type' => $off->type->value,
                'start' => $off->starts_at->getTimestamp(),
                'end' => $off->ends_at->getTimestamp(),
            ])
            ->all();

        $appointments = Appointment::withoutGlobalScopes()
            ->where('barbershop_id', $shop->id)
            ->whereIn('staff_member_id', $staffIds)
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Completed->value])
            ->where('starts_at', '<', $rangeEnd)
            ->where('blocked_until', '>', $rangeStart)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->get(['id', 'staff_member_id', 'starts_at', 'ends_at', 'blocked_until', 'duration'])
            ->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'staff' => $appointment->staff_member_id,
                'start' => $appointment->starts_at->getTimestamp(),
                'end' => $appointment->ends_at->getTimestamp(),
                'blockedUntil' => $appointment->blocked_until->getTimestamp(),
                'duration' => $appointment->duration,
            ])
            ->all();

        $settings = ShopSetting::withoutGlobalScopes()->where('barbershop_id', $shop->id)->first() ?? new ShopSetting;

        return new AvailabilityContext(
            shop: $shop,
            settings: $settings,
            staff: $eligible,
            workingHours: $workingHours,
            openingHours: $openingHours,
            hasOpeningHours: $openingRows->isNotEmpty(),
            timeOff: array_values($timeOff),
            appointments: array_values($appointments),
            now: now()->getTimestamp(),
            ignoreAppointmentId: $ignore?->id,
            online: $online,
        );
    }

    /**
     * Unie van de slots van alle kappers in de context.
     *
     * @return list<int>
     */
    private function slotTimestamps(AvailabilityContext $context, Service $service, string $date): array
    {
        $all = [];

        foreach ($context->staff as $staff) {
            foreach ($this->staffSlots($context, $service, $staff->id, $date) as $timestamp) {
                $all[$timestamp] = true;
            }
        }

        $timestamps = array_keys($all);
        sort($timestamps);

        return $timestamps;
    }

    /**
     * De slots van één kapper op een dag (§7.1, "slots").
     *
     * @return list<int>
     */
    private function staffSlots(AvailabilityContext $context, Service $service, int $staffId, string $date): array
    {
        $timezone = $context->shop->timezone;
        $settings = $context->settings;

        if ($context->online) {
            $lastDate = CarbonImmutable::createFromTimestamp($context->now, $timezone)->addDays($settings->max_days_ahead)->toDateString();

            if ($date > $lastDate) {
                return [];
            }
        }

        $earliest = $context->online ? $context->now + $settings->min_notice * 60 : $context->now;
        $interval = max(1, $settings->slot_interval);
        $duration = $service->duration * 60;
        $needed = $duration + $service->buffer_after * 60;

        [$free, $workdayEnd] = $this->freeBlocks($context, $staffId, $date);
        $slots = [];

        foreach ($free as [$blockStart, $blockEnd]) {
            // Afronden naar boven op het raster, gerekend vanaf middernacht in de tijdzone van de zaak.
            $local = CarbonImmutable::createFromTimestamp($blockStart, $timezone);
            $secondsIntoDay = $local->hour * 3600 + $local->minute * 60 + $local->second;
            $rounded = (int) (ceil($secondsIntoDay / ($interval * 60)) * $interval * 60);

            for ($start = $blockStart + ($rounded - $secondsIntoDay); $start + $duration <= $blockEnd; $start += $interval * 60) {
                // De buffer mag niet over het einde van het blok, behalve aan het einde van de werkdag.
                $bufferFits = $start + $needed <= $blockEnd || $blockEnd === $workdayEnd;

                if ($bufferFits && $start >= $earliest) {
                    $slots[] = $start;
                }
            }
        }

        return $slots;
    }

    /**
     * Past de afspraak op dit tijdstip in een vrij blok van de kapper? Zonder raster (voor kapper en inloop).
     */
    private function fits(AvailabilityContext $context, Service $service, int $staffId, string $date, int $start): bool
    {
        $duration = $service->duration * 60;
        $needed = $duration + $service->buffer_after * 60;

        [$free, $workdayEnd] = $this->freeBlocks($context, $staffId, $date);

        foreach ($free as [$blockStart, $blockEnd]) {
            if ($start >= $blockStart && $start + $duration <= $blockEnd && ($start + $needed <= $blockEnd || $blockEnd === $workdayEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vrije blokken van een kapper op een dag: werkblokken min bezette afspraken (§7.1, "vrij").
     *
     * @return array{0: list<Interval>, 1: int|null} de vrije blokken en het einde van de werkdag
     */
    private function freeBlocks(AvailabilityContext $context, int $staffId, string $date): array
    {
        $work = $this->workBlocks($context, $staffId, $date);
        $workdayEnd = $work === [] ? null : max(array_column($work, 1));
        $free = $work;

        foreach ($context->appointments as $appointment) {
            if ($appointment['staff'] === $staffId) {
                $free = $this->subtract($free, [$appointment['start'], $appointment['blockedUntil']]);
            }
        }

        return [$free, $workdayEnd];
    }

    /**
     * Werkblokken van een kapper op een dag (§7.1, "werkblokken").
     *
     * @return list<Interval>
     */
    private function workBlocks(AvailabilityContext $context, int $staffId, string $date): array
    {
        [$dayStart, $dayEnd] = $this->dayBounds($context, $date);
        $weekday = CarbonImmutable::parse($date, $context->shop->timezone)->dayOfWeekIso;

        // 2. Afwijkende uren van de kapper gaan voor de vaste werktijden.
        $custom = $this->timeOffIntervals($context, $staffId, TimeOffType::CustomHours, $dayStart, $dayEnd, shopWide: false);
        $blocks = $custom !== []
            ? $custom
            : $this->toIntervals($context, $date, $context->workingHours[$staffId][$weekday] ?? []);

        // 3. Afsnijden op de openingstijden van de zaak (als de zaak openingstijden heeft).
        $shopCustom = $this->timeOffIntervals($context, null, TimeOffType::CustomHours, $dayStart, $dayEnd, shopWide: true);

        if ($shopCustom !== []) {
            $blocks = $this->intersect($blocks, $shopCustom);
        } elseif ($context->hasOpeningHours) {
            $blocks = $this->intersect($blocks, $this->toIntervals($context, $date, $context->openingHours[$weekday] ?? []));
        }

        // 1 + 4. Gesloten periodes van de zaak én van de kapper eraf.
        foreach ($context->timeOff as $off) {
            if ($off['type'] === TimeOffType::Closed->value && ($off['staff'] === null || $off['staff'] === $staffId)) {
                $blocks = $this->subtract($blocks, [$off['start'], $off['end']]);
            }
        }

        return $blocks;
    }

    /**
     * @return list<Interval>
     */
    private function timeOffIntervals(AvailabilityContext $context, ?int $staffId, TimeOffType $type, int $dayStart, int $dayEnd, bool $shopWide): array
    {
        $intervals = [];

        foreach ($context->timeOff as $off) {
            $matches = $shopWide ? $off['staff'] === null : $off['staff'] === $staffId;

            if ($matches && $off['type'] === $type->value && $off['start'] < $dayEnd && $off['end'] > $dayStart) {
                $intervals[] = [max($off['start'], $dayStart), min($off['end'], $dayEnd)];
            }
        }

        sort($intervals);

        return $intervals;
    }

    /**
     * Zet kloktijden van een dag om naar intervallen in unix-seconden, rekening houdend met zomer- en wintertijd.
     *
     * @param  list<array{0: string, 1: string}>  $times
     * @return list<Interval>
     */
    private function toIntervals(AvailabilityContext $context, string $date, array $times): array
    {
        $timezone = $context->shop->timezone;
        $intervals = [];

        foreach ($times as [$from, $to]) {
            $start = CarbonImmutable::parse("{$date} {$from}", $timezone)->getTimestamp();
            $end = CarbonImmutable::parse("{$date} {$to}", $timezone)->getTimestamp();

            if ($end > $start) {
                $intervals[] = [$start, $end];
            }
        }

        sort($intervals);

        return $intervals;
    }

    /**
     * @return Interval
     */
    private function dayBounds(AvailabilityContext $context, string $date): array
    {
        $day = CarbonImmutable::parse($date, $context->shop->timezone)->startOfDay();

        return [$day->getTimestamp(), $day->addDay()->getTimestamp()];
    }

    /**
     * @param  list<Interval>  $intervals
     * @param  Interval  $cut
     * @return list<Interval>
     */
    private function subtract(array $intervals, array $cut): array
    {
        $result = [];

        foreach ($intervals as [$start, $end]) {
            if ($cut[1] <= $start || $cut[0] >= $end) {
                $result[] = [$start, $end];

                continue;
            }

            if ($cut[0] > $start) {
                $result[] = [$start, $cut[0]];
            }

            if ($cut[1] < $end) {
                $result[] = [$cut[1], $end];
            }
        }

        return $result;
    }

    /**
     * @param  list<Interval>  $intervals
     * @param  list<Interval>  $bounds
     * @return list<Interval>
     */
    private function intersect(array $intervals, array $bounds): array
    {
        $result = [];

        foreach ($intervals as [$start, $end]) {
            foreach ($bounds as [$boundStart, $boundEnd]) {
                $from = max($start, $boundStart);
                $to = min($end, $boundEnd);

                if ($to > $from) {
                    $result[] = [$from, $to];
                }
            }
        }

        sort($result);

        return $result;
    }
}
