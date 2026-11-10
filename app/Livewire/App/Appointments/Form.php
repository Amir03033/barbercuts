<?php

namespace App\Livewire\App\Appointments;

use App\Enums\AppointmentSource;
use App\Exceptions\BookingException;
use App\Livewire\Concerns\InShop;
use App\Livewire\Concerns\PicksSlot;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffMember;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingData;
use App\Services\Booking\CustomerInput;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Nieuwe afspraak op één scherm (§9.1): klant → dienst → kapper → tijd → opslaan. Doel: een telefonische
 * boeking in minder dan 15 seconden. Ook "Inloop nu", en hetzelfde scherm voor verplaatsen.
 */
#[Layout('layouts::app', ['active' => 'new'])]
#[Title('Nieuwe afspraak')]
class Form extends Component
{
    use InShop;
    use PicksSlot;

    /** Bij verplaatsen: de afspraak die verplaatst wordt. */
    #[Locked]
    public ?int $appointmentId = null;

    #[Url(as: 'klant')]
    public ?int $customerId = null;

    public string $search = '';

    public bool $newCustomer = false;

    public string $name = '';

    public string $phone = '';

    public ?int $serviceId = null;

    #[Url(as: 'kapper')]
    public ?int $staffId = null;

    #[Url(as: 'dag')]
    public string $date = '';

    #[Url(as: 'tijd')]
    public string $time = '';

    public string $notes = '';

    public function mount(?int $appointment = null): void
    {
        $shop = $this->shop();
        $ownStaff = $this->ownStaff();

        if ($appointment !== null) {
            $model = Appointment::query()->findOrFail($appointment);
            $this->authorize('update', $model);

            abort_unless($model->isScheduled(), 404);

            $this->appointmentId = $model->id;
            $this->customerId = $model->customer_id;
            $this->serviceId = $model->service_id;
            $this->staffId = $model->staff_member_id;
            $this->date = $model->localStart()->toDateString();
            $this->time = '';

            return;
        }

        if ($this->customerId !== null && Customer::query()->find($this->customerId) === null) {
            $this->customerId = null;
        }

        $this->serviceId = Service::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->value('id');

        // Voorgeselecteerd: jezelf. Een kapper boekt alleen in de eigen agenda.
        if (! $this->isOwner() || $this->staffId === null || $this->staffOptions()->doesntContain('id', $this->staffId)) {
            $this->staffId = $ownStaff !== null ? $ownStaff->id : ($this->isOwner() ? $this->staffOptions()->first()?->id : null);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) !== 1) {
            $this->date = CarbonImmutable::now($shop->timezone)->toDateString();
        }
    }

    protected function slotShop(): Barbershop
    {
        return $this->shop();
    }

    protected function slotService(): ?Service
    {
        if ($this->serviceId === null) {
            return null;
        }

        $appointment = $this->appointment();

        if ($appointment !== null) {
            // Verplaatsen rekent met de duur van de afspraak zelf.
            $service = clone Service::query()->findOrFail($appointment->service_id);
            $service->duration = $appointment->duration;

            return $service;
        }

        return Service::query()->where('is_active', true)->find($this->serviceId);
    }

    protected function slotStaff(): ?StaffMember
    {
        return $this->staffId === null ? null : $this->staffOptions()->firstWhere('id', $this->staffId);
    }

    protected function slotIgnore(): ?Appointment
    {
        return $this->appointment();
    }

    protected function slotOnline(): bool
    {
        return false;
    }

    private function appointment(): ?Appointment
    {
        return $this->appointmentId === null ? null : Appointment::query()->with(['customer'])->findOrFail($this->appointmentId);
    }

    private function ownStaff(): ?StaffMember
    {
        return StaffMember::query()->where('is_active', true)->where('user_id', $this->user()->id)->first();
    }

    /**
     * De kappers waaruit gekozen kan worden: de eigenaar iedereen (die de dienst doet), een kapper alleen zichzelf.
     *
     * @return Collection<int, StaffMember>
     */
    private function staffOptions(): Collection
    {
        $service = $this->serviceId === null ? null : Service::query()->find($this->serviceId);

        $staff = $service !== null
            ? app(AvailabilityService::class)->eligibleStaff($this->shop(), $service)->values()
            : StaffMember::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        return $this->isOwner() ? $staff : $staff->where('user_id', $this->user()->id)->values();
    }

    public function updatedServiceId(): void
    {
        $this->time = '';

        if ($this->staffOptions()->doesntContain('id', $this->staffId)) {
            $this->staffId = $this->staffOptions()->first()?->id;
        }
    }

    public function updatedStaffId(): void
    {
        $this->time = '';
    }

    public function chooseCustomer(int $customerId): void
    {
        $this->customerId = Customer::query()->findOrFail($customerId)->id;
        $this->search = '';
        $this->newCustomer = false;
        $this->resetErrorBag();
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
    }

    public function startNewCustomer(): void
    {
        $this->newCustomer = true;
        $this->customerId = null;

        // Wat in het zoekveld staat is meestal de naam of het nummer.
        if (preg_match('/\d{4,}/', $this->search) === 1) {
            $this->phone = $this->search;
        } else {
            $this->name = $this->search;
        }

        $this->search = '';
    }

    public function save(AppointmentService $appointments): void
    {
        $start = $this->chosenStart();

        if ($start === null) {
            $this->addError('time', 'Kies een tijd.');

            return;
        }

        $this->store($appointments, $start, AppointmentSource::Staff);
    }

    /**
     * Inloop: de klant staat in de zaak en begint nu.
     */
    public function walkIn(AppointmentService $appointments): void
    {
        abort_if($this->appointmentId !== null, 404);

        $this->store($appointments, CarbonImmutable::now('UTC')->startOfMinute(), AppointmentSource::WalkIn);
    }

    private function store(AppointmentService $appointments, CarbonImmutable $start, AppointmentSource $source): void
    {
        $shop = $this->shop();
        $staff = $this->slotStaff();

        if ($staff === null) {
            $this->addError('staffId', 'Kies een kapper.');

            return;
        }

        try {
            if ($this->appointmentId !== null) {
                $appointments->reschedule($this->appointment(), $start, $staff, $this->user());

                session()->flash('toast', 'Afspraak verplaatst');
            } else {
                $service = Service::query()->where('is_active', true)->find($this->serviceId);

                if ($service === null) {
                    $this->addError('serviceId', 'Kies een dienst.');

                    return;
                }

                $customer = $this->resolveCustomer();

                if ($customer === null) {
                    return;
                }

                $appointments->book(new BookingData($shop, $service, $staff, $start, $customer, $source, $this->notes), $this->user());

                session()->flash('toast', $source === AppointmentSource::WalkIn ? 'Inloop toegevoegd' : 'Afspraak opgeslagen');
            }
        } catch (BookingException $exception) {
            $this->addError('time', $exception->getMessage());
            $this->time = '';

            return;
        }

        $this->redirectRoute('app.calendar', [
            'dag' => $start->setTimezone($shop->timezone)->toDateString(),
            'kapper' => $staff->id,
        ]);
    }

    private function resolveCustomer(): Customer|CustomerInput|null
    {
        if ($this->customerId !== null) {
            return Customer::query()->findOrFail($this->customerId);
        }

        if (! $this->newCustomer) {
            $this->addError('customer', 'Kies een klant of voeg een nieuwe klant toe.');

            return null;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:30'],
        ], attributes: ['name' => 'naam', 'phone' => 'telefoonnummer']);

        return new CustomerInput($this->name, $this->phone);
    }

    public function render(): View
    {
        $shop = $this->shop();
        $appointment = $this->appointment();
        $search = trim($this->search);
        $digits = preg_replace('/\D+/', '', $search);

        $results = $search === '' || $this->customerId !== null ? collect() : Customer::query()
            ->where(function ($query) use ($search, $digits): void {
                $query->where('name', 'like', '%'.$search.'%');

                if (strlen((string) $digits) >= 3) {
                    $query->orWhere('phone', 'like', '%'.ltrim((string) $digits, '0').'%');
                }
            })
            ->orderBy('name')
            ->limit(6)
            ->get();

        return view('livewire.app.appointments.form', [
            'shop' => $shop,
            'appointment' => $appointment,
            'customer' => $appointment !== null ? $appointment->customer : ($this->customerId ? Customer::query()->find($this->customerId) : null),
            'results' => $results,
            'services' => $appointment ? collect() : Service::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'staffOptions' => $this->staffOptions(),
            'days' => $this->pickerDays(),
            'times' => $this->pickerTimes(),
            'canLoadMore' => $this->daysShown < $this->maxDays(),
        ])->title($appointment ? 'Afspraak verplaatsen' : 'Nieuwe afspraak');
    }
}
