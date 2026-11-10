<?php

namespace App\Livewire\App;

use App\Enums\TimeOffType;
use App\Livewire\Concerns\EditsWeekSchedule;
use App\Livewire\Concerns\InShop;
use App\Models\TimeOff;
use App\Services\ShopSetupService;
use App\Support\WeekSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Openingstijden van de zaak en dagen waarop de hele zaak dicht is (§9.1, punt 8).
 */
#[Layout('layouts::app', ['active' => 'more'])]
#[Title('Openingstijden')]
class OpeningHours extends Component
{
    use EditsWeekSchedule;
    use InShop;

    public string $closedFrom = '';

    public string $closedTo = '';

    public string $closedReason = '';

    public function mount(): void
    {
        $this->authorizeOwner();

        $this->week = WeekSchedule::fromRows($this->shop()->openingHours()->get(), 'opens_at', 'closes_at');
    }

    public function save(ShopSetupService $setup): void
    {
        $this->authorizeOwner();

        if (! $this->weekIsValid()) {
            return;
        }

        $setup->saveOpeningHours($this->shop(), $this->week);

        $this->dispatch('toast', message: 'Openingstijden opgeslagen');
    }

    public function addClosure(): void
    {
        $this->authorizeOwner();

        $this->validate([
            'closedFrom' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'closedTo' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:closedFrom'],
            'closedReason' => ['nullable', 'string', 'max:100'],
        ], attributes: ['closedFrom' => 'datum', 'closedTo' => 'einddatum', 'closedReason' => 'reden']);

        $timezone = $this->shop()->timezone;

        TimeOff::create([
            'staff_member_id' => null,
            'starts_at' => CarbonImmutable::parse($this->closedFrom, $timezone)->startOfDay()->utc(),
            'ends_at' => CarbonImmutable::parse($this->closedTo ?: $this->closedFrom, $timezone)->addDay()->startOfDay()->utc(),
            'type' => TimeOffType::Closed,
            'reason' => trim($this->closedReason) ?: null,
        ]);

        $this->reset('closedFrom', 'closedTo', 'closedReason');
        $this->dispatch('close-sheet');
        $this->dispatch('toast', message: 'Toegevoegd');
    }

    public function deleteTimeOff(int $timeOffId): void
    {
        $this->authorizeOwner();

        TimeOff::query()->whereNull('staff_member_id')->whereKey($timeOffId)->firstOrFail()->delete();

        $this->dispatch('toast', message: 'Verwijderd');
    }

    public function render(): View
    {
        return view('livewire.app.opening-hours', [
            'timezone' => $this->shop()->timezone,
            'closures' => TimeOff::query()->whereNull('staff_member_id')->where('ends_at', '>=', now())->orderBy('starts_at')->get(),
        ]);
    }
}
