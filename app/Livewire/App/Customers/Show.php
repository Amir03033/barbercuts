<?php

namespace App\Livewire\App\Customers;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Livewire\Concerns\InShop;
use App\Models\Appointment;
use App\Models\Customer;
use App\Services\Booking\CustomerResolver;
use App\Services\LoyaltyService;
use App\Services\ShopStatsService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Klantdetail (§9.1): bellen en WhatsApp, notities, bezoekhistorie, besteding en no-shows.
 * De besteding volgt de omzetregels: de eigenaar ziet het totaal, een kapper alleen wat bij hem is besteed.
 */
#[Layout('layouts::app', ['active' => 'customers'])]
#[Title('Klant')]
class Show extends Component
{
    use InShop;

    #[Locked]
    public int $customerId;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $notes = '';

    public function mount(int $customer): void
    {
        $this->customerId = $customer;
        $model = $this->customer();

        $this->authorize('view', $model);

        $this->name = $model->name;
        $this->phone = $model->phone;
        $this->email = (string) $model->email;
        $this->notes = (string) $model->notes;
    }

    private function customer(): Customer
    {
        return Customer::query()->findOrFail($this->customerId);
    }

    /**
     * De klant wil (geen) berichten van de zaak ontvangen, zoals "klanten terughalen" (fase 8).
     */
    public function setMarketing(bool $optIn): void
    {
        $customer = $this->customer();
        $this->authorize('update', $customer);

        $customer->update(['marketing_opt_in' => $optIn]);

        $this->dispatch('toast', message: $optIn ? 'Klant ontvangt berichten van de zaak' : 'Klant ontvangt geen berichten meer');
    }

    /**
     * Met de hand een stempel erbij of eraf, bijvoorbeeld bij het overnemen van een papieren spaarkaart (fase 9).
     */
    public function adjustStamps(int $delta, LoyaltyService $loyalty): void
    {
        $customer = $this->customer();
        $this->authorize('update', $customer);

        $loyalty->adjust($customer, $delta > 0 ? 1 : -1);
    }

    public function saveNotes(): void
    {
        $customer = $this->customer();
        $this->authorize('update', $customer);

        $this->validate(['notes' => ['nullable', 'string', 'max:2000']], attributes: ['notes' => 'notities']);

        $customer->update(['notes' => trim($this->notes) ?: null]);

        $this->dispatch('toast', message: 'Notitie opgeslagen');
    }

    public function saveDetails(): void
    {
        $customer = $this->customer();
        $this->authorize('update', $customer);

        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ], attributes: ['name' => 'naam', 'phone' => 'telefoonnummer', 'email' => 'e-mailadres']);

        try {
            $phone = CustomerResolver::normalizePhone($this->phone);
        } catch (BookingException $exception) {
            $this->addError('phone', $exception->getMessage());

            return;
        }

        if (Customer::query()->where('phone', $phone)->whereKeyNot($customer->id)->exists()) {
            $this->addError('phone', 'Er is al een klant met dit telefoonnummer.');

            return;
        }

        $customer->update(['name' => trim($this->name), 'phone' => $phone, 'email' => strtolower(trim($this->email)) ?: null]);
        $this->phone = $phone;

        $this->dispatch('close-sheet');
        $this->dispatch('toast', message: 'Opgeslagen');
    }

    public function render(ShopStatsService $stats): View
    {
        $shop = $this->shop();
        $customer = $this->customer();

        $appointments = Appointment::query()
            ->with('staffMember')
            ->where('customer_id', $customer->id)
            ->orderByDesc('starts_at')
            ->limit(50)
            ->get();

        return view('livewire.app.customers.show', [
            'shop' => $shop,
            'customer' => $customer,
            'appointments' => $appointments,
            'spend' => $stats->customerSpend($shop, $this->user(), $customer),
            'isOwner' => $this->isOwner(),
            'loyaltyCard' => app(LoyaltyService::class)->cardFor($shop->id),
            'visits' => $appointments->where('status', AppointmentStatus::Completed)->count(),
        ]);
    }
}
