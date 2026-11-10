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
#[Layout('layouts::app', ['active' => 'calendar'])]
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

        if ($this->staffFilter !== 'all' && ! $staff->has((int) $this->staffFilter)) {
            $this->staffFilter = 'all';
        }

        $shown = $this->staffFilter === 'all' ? $staff : $staff->only([(int) $this->staffFilter])->keyBy('id');
        $day = CarbonImmutable::parse($this->date, $timezone)->startOfDay();
        $monday = $day->startOfWeek(CarbonImmutable::MONDAY);

        $appointments = Appointment::query()
            ->with(['customer'])
            ->whereIn('staff_member_id', $shown->keys())
            ->where('starts_at', '>=', $day->utc())
            ->where('starts_at', '<', $day->addDay()->utc())
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Completed->value, AppointmentStatus::NoShow->value])
            ->orderBy('starts_at')
            ->get();

        $workBlocks = $availability->workBlocksForDay($shop, $shown, $this->date);

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

        $selected = $this->selectedAppointment();
        $today = CarbonImmutable::now($timezone);

        return view('livewire.app.calendar', [
            'shop' => $shop,
            'staff' => $staff,
            'columns' => $columns,
            'weekDays' => collect(range(0, 6))->map(fn (int $offset): array => ['date' => $monday->addDays($offset)->toDateString()])->all(),
            'day' => $day,
            'isToday' => $day->isSameDay($today),
            'hours' => range(intdiv($windowStart, 60), intdiv($windowEnd, 60) - 1),
            'windowStart' => $windowStart,
            'height' => ($windowEnd - $windowStart) * self::SCALE,
            'scale' => self::SCALE,
            'nowTop' => $day->isSameDay($today) && $today->hour * 60 + $today->minute >= $windowStart && $today->hour * 60 + $today->minute <= $windowEnd
                ? ($today->hour * 60 + $today->minute - $windowStart) * self::SCALE
                : null,
            'interval' => (ShopSetting::query()->first() ?? new ShopSetting)->slot_interval,
            'selected' => $selected,
            'canUpdateSelected' => $selected !== null && $this->user()->can('update', $selected),
        ]);
    }
}
