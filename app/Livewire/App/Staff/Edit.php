<?php

namespace App\Livewire\App\Staff;

use App\Enums\TimeOffType;
use App\Livewire\Concerns\EditsWeekSchedule;
use App\Livewire\Concerns\InShop;
use App\Models\StaffMember;
use App\Notifications\StaffInvitation;
use App\Services\ShopSetupService;
use App\Support\Color;
use App\Support\WeekSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Eén kapper: profiel (eigenaar), werktijden met pauzes en vrije dagen (eigenaar of de kapper zelf).
 */
#[Layout('layouts::app', ['active' => 'more'])]
#[Title('Kapper')]
class Edit extends Component
{
    use EditsWeekSchedule;
    use InShop;

    #[Locked]
    public int $staffId;

    public string $name = '';

    public string $color = '';

    public string $bio = '';

    public bool $isActive = true;

    public string $inviteEmail = '';

    // Vrij / afwijkende uren
    public string $offType = 'closed';

    public string $offFrom = '';

    public string $offTo = '';

    public string $offStart = '09:00';

    public string $offEnd = '21:00';

    public string $offReason = '';

    public function mount(int $staffMember): void
    {
        $this->staffId = $staffMember;
        $staff = $this->staff();

        $this->authorize('updateSchedule', $staff);

        $this->name = $staff->name;
        $this->color = $staff->color;
        $this->bio = (string) $staff->bio;
        $this->isActive = $staff->is_active;
        $this->inviteEmail = (string) $staff->invited_email;
        $this->week = WeekSchedule::fromRows($staff->workingHours()->get(), 'starts_at', 'ends_at');
    }

    /**
     * De kapper binnen de huidige zaak; een id van een andere zaak geeft 404 (§6.3).
     */
    private function staff(): StaffMember
    {
        return StaffMember::query()->findOrFail($this->staffId);
    }

    public function saveProfile(): void
    {
        $staff = $this->staff();
        $this->authorize('update', $staff);

        $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', Rule::in(Color::STAFF_COLORS)],
            'bio' => ['nullable', 'string', 'max:500'],
        ], attributes: ['name' => 'naam', 'color' => 'kleur', 'bio' => 'over']);

        $staff->update([
            'name' => trim($this->name),
            'color' => $this->color,
            'bio' => trim($this->bio) ?: null,
            'is_active' => $this->isActive,
        ]);

        $this->dispatch('toast', message: 'Opgeslagen');
    }

    public function saveHours(ShopSetupService $setup): void
    {
        $staff = $this->staff();
        $this->authorize('updateSchedule', $staff);

        if (! $this->weekIsValid()) {
            return;
        }

        $setup->saveWorkingHours($staff, $this->week);

        $this->dispatch('toast', message: 'Werktijden opgeslagen');
    }

    public function addTimeOff(): void
    {
        $staff = $this->staff();
        $this->authorize('updateSchedule', $staff);

        $custom = $this->offType === TimeOffType::CustomHours->value;

        $this->validate([
            'offType' => ['required', Rule::enum(TimeOffType::class)],
            'offFrom' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'offTo' => $custom ? ['nullable'] : ['nullable', 'date_format:Y-m-d', 'after_or_equal:offFrom'],
            'offStart' => $custom ? ['required', 'date_format:H:i'] : ['nullable'],
            'offEnd' => $custom ? ['required', 'date_format:H:i', 'after:offStart'] : ['nullable'],
            'offReason' => ['nullable', 'string', 'max:100'],
        ], attributes: [
            'offFrom' => 'datum', 'offTo' => 'einddatum', 'offStart' => 'begintijd', 'offEnd' => 'eindtijd', 'offReason' => 'reden',
        ]);

        $timezone = $this->shop()->timezone;

        if ($custom) {
            $startsAt = CarbonImmutable::parse("{$this->offFrom} {$this->offStart}", $timezone);
            $endsAt = CarbonImmutable::parse("{$this->offFrom} {$this->offEnd}", $timezone);
        } else {
            $startsAt = CarbonImmutable::parse($this->offFrom, $timezone)->startOfDay();
            $endsAt = CarbonImmutable::parse($this->offTo ?: $this->offFrom, $timezone)->addDay()->startOfDay();
        }

        $staff->timeOff()->create([
            'starts_at' => $startsAt->utc(),
            'ends_at' => $endsAt->utc(),
            'type' => $this->offType,
            'reason' => trim($this->offReason) ?: null,
        ]);

        $this->reset('offFrom', 'offTo', 'offReason');
        $this->dispatch('close-sheet');
        $this->dispatch('toast', message: 'Toegevoegd');
    }

    public function deleteTimeOff(int $timeOffId): void
    {
        $staff = $this->staff();
        $this->authorize('updateSchedule', $staff);

        $staff->timeOff()->whereKey($timeOffId)->firstOrFail()->delete();

        $this->dispatch('toast', message: 'Verwijderd');
    }

    public function invite(): void
    {
        $staff = $this->staff();
        $this->authorize('update', $staff);

        abort_if($staff->user_id !== null, 422);

        $this->validate([
            'inviteEmail' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ], attributes: ['inviteEmail' => 'e-mailadres']);

        $staff->invited_email = $this->inviteEmail;
        $staff->invited_at = now();
        $staff->save();

        Notification::route('mail', $this->inviteEmail)->notify(new StaffInvitation($staff, $this->shop(), $this->user()->name));

        $this->dispatch('toast', message: 'Uitnodiging verstuurd');
    }

    public function delete(): void
    {
        $staff = $this->staff();
        $this->authorize('delete', $staff);

        if ($staff->appointments()->exists()) {
            $this->dispatch('toast', message: 'Deze kapper heeft afspraken. Zet de kapper op niet actief.', type: 'error');

            return;
        }

        if (StaffMember::query()->count() <= 1) {
            $this->dispatch('toast', message: 'Je zaak heeft minstens één kapper nodig.', type: 'error');

            return;
        }

        $staff->delete();

        session()->flash('toast', 'Kapper verwijderd');
        $this->redirectRoute('app.staff');
    }

    public function render(): View
    {
        $staff = $this->staff();
        $timezone = $this->shop()->timezone;

        return view('livewire.app.staff.edit', [
            'staff' => $staff,
            'canManage' => $this->isOwner(),
            'colors' => Color::STAFF_COLORS,
            'timezone' => $timezone,
            'timeOff' => $staff->timeOff()->where('ends_at', '>=', now())->orderBy('starts_at')->get(),
        ]);
    }
}
