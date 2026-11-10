<?php

namespace App\Livewire\App;

use App\Livewire\Concerns\InShop;
use App\Models\ShopSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Boekregels en herinneringen (§9.1, punt 9 en B14).
 */
#[Layout('layouts::app', ['active' => 'more'])]
#[Title('Instellingen')]
class Settings extends Component
{
    use InShop;

    public const array SLOT_INTERVALS = [5 => '5 minuten', 10 => '10 minuten', 15 => '15 minuten', 20 => '20 minuten', 30 => '30 minuten', 60 => '60 minuten'];

    public const array NOTICE_OPTIONS = [0 => 'Geen', 30 => '30 minuten', 60 => '1 uur', 120 => '2 uur', 240 => '4 uur', 720 => '12 uur', 1440 => '24 uur'];

    public const array LIMIT_OPTIONS = [0 => 'Tot het begin', 60 => '1 uur vooraf', 120 => '2 uur vooraf', 240 => '4 uur vooraf', 720 => '12 uur vooraf', 1440 => '24 uur vooraf', 2880 => '48 uur vooraf'];

    public const array REMINDER_1_OPTIONS = [1440 => '24 uur vooraf', 720 => '12 uur vooraf', 180 => '3 uur vooraf'];

    public const array REMINDER_2_OPTIONS = [180 => '3 uur vooraf', 120 => '2 uur vooraf', 60 => '1 uur vooraf'];

    public string $slotInterval = '15';

    public string $minNotice = '60';

    public string $maxDaysAhead = '60';

    public string $cancelLimit = '120';

    public string $rescheduleLimit = '120';

    public bool $allowGuestBooking = true;

    public bool $allowStaffChoice = true;

    /** Minuten, of "custom" voor een eigen aantal uren. */
    public string $reminder1 = '1440';

    public string $reminder1Hours = '';

    /** Minuten, "off" of "custom". */
    public string $reminder2 = 'off';

    public string $reminder2Hours = '';

    public function mount(): void
    {
        $this->authorizeOwner();

        $settings = $this->settings();

        $this->slotInterval = (string) $settings->slot_interval;
        $this->minNotice = (string) $settings->min_notice;
        $this->maxDaysAhead = (string) $settings->max_days_ahead;
        $this->cancelLimit = (string) $settings->customer_cancel_limit;
        $this->rescheduleLimit = (string) $settings->customer_reschedule_limit;
        $this->allowGuestBooking = $settings->allow_guest_booking;
        $this->allowStaffChoice = $settings->allow_staff_choice;

        if (isset(self::REMINDER_1_OPTIONS[$settings->reminder_1_minutes])) {
            $this->reminder1 = (string) $settings->reminder_1_minutes;
        } else {
            $this->reminder1 = 'custom';
            $this->reminder1Hours = self::hours($settings->reminder_1_minutes);
        }

        if ($settings->reminder_2_minutes === null) {
            $this->reminder2 = 'off';
        } elseif (isset(self::REMINDER_2_OPTIONS[$settings->reminder_2_minutes])) {
            $this->reminder2 = (string) $settings->reminder_2_minutes;
        } else {
            $this->reminder2 = 'custom';
            $this->reminder2Hours = self::hours($settings->reminder_2_minutes);
        }
    }

    private function settings(): ShopSetting
    {
        return ShopSetting::query()->firstOrCreate([]);
    }

    private static function hours(int $minutes): string
    {
        return rtrim(rtrim(number_format($minutes / 60, 1, ',', ''), '0'), ',');
    }

    private static function minutesFromHours(string $hours): int
    {
        return (int) round(((float) str_replace(',', '.', $hours)) * 60);
    }

    public function save(): void
    {
        $this->authorizeOwner();

        $hoursRule = ['required', 'regex:/^\d{1,3}([.,]\d)?$/'];

        $this->validate([
            'slotInterval' => ['required', Rule::in(array_keys(self::SLOT_INTERVALS))],
            'minNotice' => ['required', Rule::in(array_keys(self::NOTICE_OPTIONS))],
            'maxDaysAhead' => ['required', 'integer', 'min:1', 'max:365'],
            'cancelLimit' => ['required', Rule::in(array_keys(self::LIMIT_OPTIONS))],
            'rescheduleLimit' => ['required', Rule::in(array_keys(self::LIMIT_OPTIONS))],
            'reminder1' => ['required', Rule::in([...array_keys(self::REMINDER_1_OPTIONS), 'custom'])],
            'reminder1Hours' => $this->reminder1 === 'custom' ? $hoursRule : ['nullable'],
            'reminder2' => ['required', Rule::in([...array_keys(self::REMINDER_2_OPTIONS), 'off', 'custom'])],
            'reminder2Hours' => $this->reminder2 === 'custom' ? $hoursRule : ['nullable'],
        ], messages: [
            'reminder1Hours' => 'Vul een aantal uren in, bijvoorbeeld 6.',
            'reminder2Hours' => 'Vul een aantal uren in, bijvoorbeeld 1,5.',
        ], attributes: ['maxDaysAhead' => 'aantal dagen']);

        $reminder1 = $this->reminder1 === 'custom' ? self::minutesFromHours($this->reminder1Hours) : (int) $this->reminder1;
        $reminder2 = match ($this->reminder2) {
            'off' => null,
            'custom' => self::minutesFromHours($this->reminder2Hours),
            default => (int) $this->reminder2,
        };

        if ($reminder1 < 15) {
            $this->addError('reminder1Hours', 'De herinnering moet minstens een kwartier vooraf zijn.');

            return;
        }

        if ($reminder2 !== null && ($reminder2 < 15 || $reminder2 >= $reminder1)) {
            $this->addError($this->reminder2 === 'custom' ? 'reminder2Hours' : 'reminder2', 'De 2e herinnering moet later zijn dan de 1e.');

            return;
        }

        $this->settings()->update([
            'slot_interval' => (int) $this->slotInterval,
            'min_notice' => (int) $this->minNotice,
            'max_days_ahead' => (int) $this->maxDaysAhead,
            'customer_cancel_limit' => (int) $this->cancelLimit,
            'customer_reschedule_limit' => (int) $this->rescheduleLimit,
            'allow_guest_booking' => $this->allowGuestBooking,
            'allow_staff_choice' => $this->allowStaffChoice,
            'reminder_1_minutes' => $reminder1,
            'reminder_2_minutes' => $reminder2,
        ]);

        $this->dispatch('toast', message: 'Instellingen opgeslagen');
    }

    public function render(): View
    {
        return view('livewire.app.settings');
    }
}
