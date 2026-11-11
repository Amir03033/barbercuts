<?php

namespace App\Livewire\App;

use App\Enums\AppointmentStatus;
use App\Livewire\Concerns\HandlesAppointmentActions;
use App\Livewire\Concerns\InShop;
use App\Models\Appointment;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use App\Services\Booking\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Agenda (§9.1): weekstrip + dagweergave als tijdlijn, per kapper of iedereen naast elkaar.
 * Iedereen in de zaak ziet alle agenda's; wijzigen kan alleen volgens de policy (collega's alleen-lezen).
 */
#[Layout('layouts::app', ['active' => 'calendar', 'wide' => true])]
#[Title('Agenda')]
class Calendar extends Component
{
    use HandlesAppointmentActions;
    use InShop;

    /** Pixels per minuut in de tijdlijn. */
    private const float SCALE = 1.6;

    #[Url(as: 'dag')]
    public string $date = '';

    /** 'all' of het id van een kapper. */
    #[Url(as: 'kapper')]
    public string $staffFilter = '';

    /** Weergave: day (tijdlijn), week of month. */
    #[Url(as: 'weergave')]
    public string $view = 'day';

    public function mount(): void
    {
        $shop = $this->shop();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) !== 1) {
            $this->date = CarbonImmutable::now($shop->timezone)->toDateString();
        }

        if ($this->staffFilter === '') {
            // Standaard de eigen agenda; de eigenaar zonder agenda ziet iedereen.
            $own = $this->staff()->firstWhere('user_id', $this->user()->id);
            $this->staffFilter = $own !== null ? (string) $own->id : 'all';
        }
    }

    public function updatedView(): void
    {
        if (! in_array($this->view, ['day', 'week', 'month'], true)) {
            $this->view = 'day';
        }
    }

    /**
     * Eén stap vooruit of achteruit in de huidige weergave: een dag, een week of een maand.
     */
    public function shift(int $direction): void
    {
        $direction = $direction < 0 ? -1 : 1;
        $date = CarbonImmutable::parse($this->date, $this->shop()->timezone);

        $next = match ($this->view) {
            'week' => $date->addWeeks($direction),
            'month' => $date->addMonthsNoOverflow($direction),
            default => $date->addDays($direction),
        };

        $this->date = $next->toDateString();
    }

    /**
     * Een dag in de week- of maandweergave kiezen opent de dagweergave.
     */
    public function selectDay(string $date): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return;
        }

        $this->date = $date;
        $this->view = 'day';
    }

    /**
     * @return Collection<int, StaffMember>
     */
    private function staff(): Collection
    {
        return StaffMember::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->keyBy('id');
    }

    public function shiftWeek(int $weeks): void
    {
        $this->date = CarbonImmutable::parse($this->date, $this->shop()->timezone)->addWeeks(max(-1, min(1, $weeks)))->toDateString();
    }

    public function goToToday(): void
    {
        $this->date = CarbonImmutable::now($this->shop()->timezone)->toDateString();
    }

    /**
     * Tik op een lege plek: nieuwe afspraak op dat tijdstip bij die kapper.
     */
    public function newAt(int $staffId, int $minutes): void
    {
        abort_unless($this->staff()->has($staffId), 404);

        $minutes = max(0, min(24 * 60 - 1, $minutes));

        $this->redirectRoute('app.appointments.create', [
            'kapper' => $staffId,
            'dag' => $this->date,
            'tijd' => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60),
        ], navigate: true);
    }

    public function render(AvailabilityService $availability): View
    {
        $shop = $this->shop();
        $timezone = $shop->timezone;
        $staff = $this->staff();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) !== 1) {
            $this->date = CarbonImmutable::now($timezone)->toDateString();
        }

        if (! in_array($this->view, ['day', 'week', 'month'], true)) {
            $this->view = 'day';
        }

        if ($this->staffFilter !== 'all' && ! $staff->has((int) $this->staffFilter)) {
            $this->staffFilter = 'all';
        }

        $shown = $this->staffFilter === 'all' ? $staff : $staff->only([(int) $this->staffFilter])->keyBy('id');
        $day = CarbonImmutable::parse($this->date, $timezone)->startOfDay();
        $today = CarbonImmutable::now($timezone);
        $selected = $this->selectedAppointment();

        $data = match ($this->view) {
            'week' => $this->weekData($shown, $day),
            'month' => $this->monthData($shown, $day, $today),
            default => $this->dayData($availability, $shown, $day, $today),
        };

        return view('livewire.app.calendar', [
            'shop' => $shop,
            'staff' => $staff,
            'view' => $this->view,
            'day' => $day,
            'isToday' => $day->isSameDay($today),
            'periodLabel' => match ($this->view) {
                'week' => 'Week '.$day->isoWeek.' · '.nl_date($day->startOfWeek(CarbonImmutable::MONDAY), 'D MMM').' – '.nl_date($day->endOfWeek(CarbonImmutable::SUNDAY), 'D MMM'),
                'month' => ucfirst(nl_date($day, 'MMMM YYYY')),
                default => ucfirst(nl_date($day, 'dddd D MMMM')),
            },
            'weekDays' => collect(range(0, 6))->map(fn (int $offset): array => ['date' => $day->startOfWeek(CarbonImmutable::MONDAY)->addDays($offset)->toDateString()])->all(),
            'scale' => self::SCALE,
            'interval' => (ShopSetting::query()->first() ?? new ShopSetting)->slot_interval,
            'selected' => $selected,
            'canUpdateSelected' => $selected !== null && $this->user()->can('update', $selected),
            ...$data,
        ]);
    }

    /**
     * Dagweergave: per kapper een kolom met werktijden, pauzes en afspraken.
     *
     * @param  Collection<int, StaffMember>  $shown
     * @return array<string, mixed>
     */
    private function dayData(AvailabilityService $availability, Collection $shown, CarbonImmutable $day, CarbonImmutable $today): array
    {
        $timezone = $this->shop()->timezone;

        $appointments = Appointment::query()
            ->with(['customer'])
            ->whereIn('staff_member_id', $shown->keys())
            ->where('starts_at', '>=', $day->utc())
            ->where('starts_at', '<', $day->addDay()->utc())
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Completed->value, AppointmentStatus::NoShow->value])
            ->orderBy('starts_at')
            ->get();

        $workBlocks = $availability->workBlocksForDay($this->shop(), $shown, $this->date);

        // Het venster van de tijdlijn: van de vroegste tot de laatste werktijd of afspraak, op hele uren.
        $starts = [];
        $ends = [];

        foreach ($workBlocks as $blocks) {
            foreach ($blocks as [$from, $to]) {
                $starts[] = $from->setTimezone($timezone);
                $ends[] = $to->setTimezone($timezone);
            }
        }

        foreach ($appointments as $appointment) {
            $starts[] = $appointment->starts_at->toImmutable()->setTimezone($timezone);
            $ends[] = $appointment->ends_at->toImmutable()->setTimezone($timezone);
        }

        $windowStart = $starts === [] ? 9 * 60 : intdiv(min(array_map(fn ($time) => $time->hour * 60 + $time->minute, $starts)), 60) * 60;
        $windowEnd = $ends === [] ? 18 * 60 : (int) ceil(max(array_map(fn ($time) => $time->isSameDay($day) ? $time->hour * 60 + $time->minute : 24 * 60, $ends)) / 60) * 60;
        $windowEnd = max($windowEnd, $windowStart + 60);

        $minuteOf = fn (CarbonImmutable $time): int => $time->isSameDay($day) ? $time->hour * 60 + $time->minute : ($time < $day ? 0 : 24 * 60);

        $columns = [];

        foreach ($shown as $member) {
            // Niet-werktijd (pauze, vrij, voor en na werktijd) wordt grijs gearceerd.
            $offBlocks = [];
            $cursor = $windowStart;

            foreach ($workBlocks[$member->id] ?? [] as [$from, $to]) {
                $fromMinute = max($windowStart, $minuteOf($from->setTimezone($timezone)));
                $toMinute = min($windowEnd, $minuteOf($to->setTimezone($timezone)));

                if ($fromMinute > $cursor) {
                    $offBlocks[] = ['top' => ($cursor - $windowStart) * self::SCALE, 'height' => ($fromMinute - $cursor) * self::SCALE];
                }

                $cursor = max($cursor, $toMinute);
            }

            if ($cursor < $windowEnd) {
                $offBlocks[] = ['top' => ($cursor - $windowStart) * self::SCALE, 'height' => ($windowEnd - $cursor) * self::SCALE];
            }

            $columns[] = [
                'staff' => $member,
                'off' => $offBlocks,
                'appointments' => $appointments->where('staff_member_id', $member->id)->map(function (Appointment $appointment) use ($timezone, $windowStart, $minuteOf): array {
                    $start = $appointment->starts_at->toImmutable()->setTimezone($timezone);

                    return [
                        'model' => $appointment,
                        'time' => $start->format('H:i'),
                        'top' => ($minuteOf($start) - $windowStart) * self::SCALE,
                        'height' => max(22, $appointment->duration * self::SCALE - 2),
                    ];
                })->values()->all(),
            ];
        }

        $nowMinute = $today->hour * 60 + $today->minute;

        return [
            'columns' => $columns,
            'hours' => range(intdiv($windowStart, 60), intdiv($windowEnd, 60) - 1),
            'windowStart' => $windowStart,
            'height' => ($windowEnd - $windowStart) * self::SCALE,
            'nowTop' => $day->isSameDay($today) && $nowMinute >= $windowStart && $nowMinute <= $windowEnd ? ($nowMinute - $windowStart) * self::SCALE : null,
        ];
    }

    /**
     * Weekweergave: zeven dagkolommen met de afspraken van de gekozen kapper(s), gekleurd per kapper.
     *
     * @param  Collection<int, StaffMember>  $shown
     * @return array<string, mixed>
     */
    private function weekData(Collection $shown, CarbonImmutable $day): array
    {
        $timezone = $this->shop()->timezone;
        $monday = $day->startOfWeek(CarbonImmutable::MONDAY);
        $today = CarbonImmutable::now($timezone);

        $appointments = Appointment::query()
            ->with(['customer', 'staffMember'])
            ->whereIn('staff_member_id', $shown->keys())
            ->where('starts_at', '>=', $monday->utc())
            ->where('starts_at', '<', $monday->addWeek()->utc())
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Completed->value, AppointmentStatus::NoShow->value])
            ->orderBy('starts_at')
            ->get();

        $minutes = fn (CarbonImmutable $time): int => $time->hour * 60 + $time->minute;
        $local = fn (Appointment $appointment): CarbonImmutable => $appointment->starts_at->toImmutable()->setTimezone($timezone);

        $windowStart = $appointments->isEmpty() ? 9 * 60 : min(9 * 60, intdiv($appointments->map(fn ($a) => $minutes($local($a)))->min(), 60) * 60);
        $windowEnd = $appointments->isEmpty() ? 18 * 60 : max(18 * 60, (int) ceil($appointments->map(fn ($a) => $minutes($local($a)) + $a->duration)->max() / 60) * 60);
        $windowEnd = min(24 * 60, $windowEnd);

        $columns = [];

        foreach (range(0, 6) as $offset) {
            $date = $monday->addDays($offset);

            $columns[] = [
                'date' => $date->toDateString(),
                'label' => rtrim($date->locale('nl')->isoFormat('dd'), '.'),
                'number' => $date->day,
                'isToday' => $date->isSameDay($today),
                'appointments' => $appointments
                    ->filter(fn (Appointment $appointment): bool => $local($appointment)->isSameDay($date))
                    ->map(fn (Appointment $appointment): array => [
                        'model' => $appointment,
                        'time' => $local($appointment)->format('H:i'),
                        'top' => ($minutes($local($appointment)) - $windowStart) * self::SCALE,
                        'height' => max(22, $appointment->duration * self::SCALE - 2),
                    ])->values()->all(),
            ];
        }

        return [
            'weekColumns' => $columns,
            'hours' => range(intdiv($windowStart, 60), intdiv($windowEnd, 60) - 1),
            'height' => ($windowEnd - $windowStart) * self::SCALE,
            'multiStaff' => $shown->count() > 1,
        ];
    }

    /**
     * Maandweergave: een raster met per dag het aantal afspraken en gekleurde stippen per kapper.
     *
     * @param  Collection<int, StaffMember>  $shown
     * @return array<string, mixed>
     */
    private function monthData(Collection $shown, CarbonImmutable $day, CarbonImmutable $today): array
    {
        $timezone = $this->shop()->timezone;
        $first = $day->startOfMonth();
        $gridStart = $first->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $first->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay()->addDay();

        $perDay = Appointment::query()
            ->whereIn('staff_member_id', $shown->keys())
            ->where('starts_at', '>=', $gridStart->utc())
            ->where('starts_at', '<', $gridEnd->utc())
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Completed->value])
            ->get(['id', 'staff_member_id', 'starts_at'])
            ->groupBy(fn (Appointment $appointment): string => $appointment->starts_at->toImmutable()->setTimezone($timezone)->toDateString());

        $weeks = [];

        for ($cursor = $gridStart; $cursor < $gridEnd; $cursor = $cursor->addWeek()) {
            $cells = [];

            foreach (range(0, 6) as $offset) {
                $date = $cursor->addDays($offset);
                $items = $perDay->get($date->toDateString(), collect());

                $cells[] = [
                    'date' => $date->toDateString(),
                    'number' => $date->day,
                    'inMonth' => $date->month === $first->month,
                    'isToday' => $date->isSameDay($today),
                    'count' => $items->count(),
                    'colors' => $items->pluck('staff_member_id')->unique()->take(4)->map(fn (int $id): string => $shown->has($id) ? $shown[$id]->color : '#9a9890')->values()->all(),
                ];
            }

            $weeks[] = $cells;
        }

        return ['weeks' => $weeks];
    }
}
