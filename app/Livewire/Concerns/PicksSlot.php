<?php

namespace App\Livewire\Concerns;

use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use App\Services\Booking\AvailabilityService;
use Carbon\CarbonImmutable;

/**
 * Dag + tijd kiezen op één scherm (§9.2): datumstrip met tijden als chips. Hoort bij <x-shop.slot-picker>.
 */
trait PicksSlot
{
    public string $date = '';

    public string $time = '';

    public int $daysShown = 14;

    abstract protected function slotShop(): Barbershop;

    abstract protected function slotService(): ?Service;

    abstract protected function slotStaff(): ?StaffMember;

    protected function slotIgnore(): ?Appointment
    {
        return null;
    }

    public function updatedDate(): void
    {
        $this->time = '';
    }

    public function showMoreDays(): void
    {
        $this->daysShown = min($this->daysShown + 14, $this->maxDays());
    }

    protected function maxDays(): int
    {
        $settings = ShopSetting::withoutGlobalScopes()->where('barbershop_id', $this->slotShop()->id)->first();

        return ($settings->max_days_ahead ?? 60) + 1;
    }

    /**
     * De dagen voor de datumstrip; dagen zonder vrije tijd zijn uitgeschakeld.
     *
     * @return list<array{date: string, disabled: bool}>
     */
    protected function pickerDays(): array
    {
        $service = $this->slotService();

        if ($service === null) {
            return [];
        }

        $shop = $this->slotShop();
        $today = CarbonImmutable::now($shop->timezone);

        $available = app(AvailabilityService::class)->availableDays(
            $shop, $service, $this->slotStaff(),
            $today->toDateString(), $today->addDays(min($this->daysShown, $this->maxDays()) - 1)->toDateString(),
            $this->slotIgnore(),
        );

        $days = [];

        foreach ($available as $date => $isAvailable) {
            $days[] = ['date' => $date, 'disabled' => ! $isAvailable];
        }

        // Kies de eerste dag met vrije tijden als er nog geen (geldige) dag gekozen is.
        if (! ($available[$this->date] ?? false)) {
            $this->date = (string) (array_key_first(array_filter($available)) ?? '');
            $this->time = '';
        }

        return $days;
    }

    /**
     * Vrije tijden op de gekozen dag als kloktijden.
     *
     * @return list<string>
     */
    protected function pickerTimes(): array
    {
        $service = $this->slotService();

        if ($service === null || $this->date === '') {
            return [];
        }

        $shop = $this->slotShop();
        $slots = app(AvailabilityService::class)->slots($shop, $service, $this->slotStaff(), $this->date, $this->slotIgnore());

        return array_map(fn (CarbonImmutable $slot): string => $slot->setTimezone($shop->timezone)->format('H:i'), $slots);
    }

    /**
     * Tijden gegroepeerd in Ochtend / Middag / Avond.
     *
     * @return array<string, list<string>>
     */
    protected function pickerSlots(): array
    {
        $groups = ['Ochtend' => [], 'Middag' => [], 'Avond' => []];

        foreach ($this->pickerTimes() as $time) {
            $groups[$time < '12:00' ? 'Ochtend' : ($time < '18:00' ? 'Middag' : 'Avond')][] = $time;
        }

        return array_filter($groups);
    }

    /**
     * Het gekozen tijdstip in UTC, of null als er geen geldige dag en tijd is gekozen.
     */
    protected function chosenStart(): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) !== 1 || preg_match('/^\d{2}:\d{2}$/', $this->time) !== 1) {
            return null;
        }

        return CarbonImmutable::parse("{$this->date} {$this->time}", $this->slotShop()->timezone)->utc();
    }
}
