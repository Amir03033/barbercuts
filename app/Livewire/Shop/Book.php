<?php

namespace App\Livewire\Shop;

use App\Exceptions\BookingException;
use App\Livewire\Concerns\PicksSlot;
use App\Models\Barbershop;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingData;
use App\Services\Booking\CustomerInput;
use App\Services\Booking\CustomerResolver;
use App\Support\CurrentShop;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Boekflow in maximaal vier stappen: dienst → kapper → dag + tijd → gegevens (§9.2).
 * De keuzes staan in de URL, zodat een klant na inloggen verdergaat waar hij was.
 */
class Book extends Component
{
    use PicksSlot;

    public string $step = 'service';

    #[Url(as: 'dienst')]
    public ?int $serviceId = null;

    /** '' = nog niet gekozen, 'any' = geen voorkeur, anders het id van de kapper. */
    #[Url(as: 'kapper')]
    public string $staffChoice = '';

    #[Url(as: 'dag')]
    public string $date = '';

    #[Url(as: 'tijd')]
    public string $time = '';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $notes = '';

    /** Honeypot: mensen zien dit veld niet, bots vullen het in. */
    public string $website = '';

    public function mount(): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $this->name = $user->name;
            $this->email = $user->email;
            $this->phone = (string) $user->phone;
        }

        // Verdergaan vanuit de URL (na inloggen of "opnieuw boeken").
        if ($this->service() === null) {
            $this->serviceId = null;
            $this->step = 'service';

            return;
        }

        if ($this->staffChoice === '' || ($this->staffChoice !== 'any' && $this->staff() === null)) {
            $this->staffChoice = '';
            $this->afterService();

            return;
        }

        $this->step = $this->chosenStart() !== null && in_array($this->time, $this->pickerTimes(), true) ? 'details' : 'time';
    }

    protected function slotShop(): Barbershop
    {
        $shop = app(CurrentShop::class)->get();

        abort_if($shop === null, 404);

        return $shop;
    }

    protected function slotService(): ?Service
    {
        return $this->service();
    }

    protected function slotStaff(): ?StaffMember
    {
        return $this->staff();
    }

    private function settings(): ShopSetting
    {
        return ShopSetting::query()->first() ?? new ShopSetting;
    }

    private function service(): ?Service
    {
        return $this->serviceId === null ? null : Service::query()->where('is_active', true)->find($this->serviceId);
    }

    /**
     * De gekozen kapper; null bij "geen voorkeur".
     */
    private function staff(): ?StaffMember
    {
        if ($this->staffChoice === '' || $this->staffChoice === 'any') {
            return null;
        }

        $service = $this->service();

        return $service === null ? null : $this->eligibleStaff($service)->get((int) $this->staffChoice);
    }

    /**
     * @return Collection<int, StaffMember>
     */
    private function eligibleStaff(Service $service): Collection
    {
        return app(AvailabilityService::class)->eligibleStaff($this->slotShop(), $service);
    }

    /**
     * De stap komt uit de browser: alleen bekende stappen, en nooit verder dan de gemaakte keuzes toelaten.
     */
    public function updatedStep(): void
    {
        if (! in_array($this->step, ['service', 'staff', 'time', 'details'], true) || $this->service() === null) {
            $this->step = 'service';
        } elseif ($this->step === 'details' && ($this->chosenStart() === null || ! in_array($this->time, $this->pickerTimes(), true))) {
            $this->step = 'time';
        }
    }

    public function chooseService(int $serviceId): void
    {
        $this->serviceId = $serviceId;

        if ($this->service() === null) {
            $this->serviceId = null;

            return;
        }

        $this->staffChoice = '';
        $this->date = '';
        $this->time = '';
        $this->afterService();
    }

    /**
     * Sla de kapperstap over als er maar één kapper is of de zaak de klant niet laat kiezen.
     */
    private function afterService(): void
    {
        $eligible = $this->eligibleStaff($this->service());

        if ($eligible->count() === 1) {
            $this->staffChoice = (string) $eligible->keys()->first();
            $this->step = 'time';
        } elseif (! $this->settings()->allow_staff_choice || $eligible->isEmpty()) {
            $this->staffChoice = 'any';
            $this->step = 'time';
        } else {
            $this->step = 'staff';
        }
    }

    private function hasStaffStep(): bool
    {
        $service = $this->service();

        return $service !== null && $this->settings()->allow_staff_choice && $this->eligibleStaff($service)->count() > 1;
    }

    public function chooseStaff(string $choice): void
    {
        $this->staffChoice = $choice;

        if ($choice !== 'any' && $this->staff() === null) {
            $this->staffChoice = '';

            return;
        }

        $this->date = '';
        $this->time = '';
        $this->step = 'time';
    }

    public function confirmTime(): void
    {
        if ($this->chosenStart() === null || ! in_array($this->time, $this->pickerTimes(), true)) {
            $this->time = '';
            $this->addError('time', 'Kies een tijd.');

            return;
        }

        $this->step = 'details';
    }

    public function back(): void
    {
        $this->resetErrorBag();

        $this->step = match ($this->step) {
            'details' => 'time',
            'time' => $this->hasStaffStep() ? 'staff' : 'service',
            default => 'service',
        };
    }

    /**
     * Eerst inloggen of een account maken; daarna terug naar deze boeking.
     */
    public function continueWithAccount(string $route): void
    {
        abort_unless(in_array($route, ['login', 'register'], true), 404);

        session(['url.intended' => route('shop.book', array_filter([
            'slug' => $this->slotShop()->slug,
            'dienst' => $this->serviceId,
            'kapper' => $this->staffChoice,
            'dag' => $this->date,
            'tijd' => $this->time,
        ]))]);

        $this->redirectRoute($route);
    }

    public function book(AppointmentService $appointments): void
    {
        $shop = $this->slotShop();
        $service = $this->service();
        $start = $this->chosenStart();

        if ($service === null || $start === null) {
            $this->step = 'service';

            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], attributes: ['name' => 'naam', 'phone' => 'telefoonnummer', 'email' => 'e-mailadres', 'notes' => 'opmerking']);

        // Een ingevuld honeypot-veld is een bot: geen afspraak, geen aanwijzing waarom.
        if ($this->website !== '') {
            $this->addError('booking', 'Boeken is nu niet gelukt. Probeer het later opnieuw of bel de zaak.');

            return;
        }

        try {
            $phone = CustomerResolver::normalizePhone($this->phone);
        } catch (BookingException $exception) {
            $this->addError('phone', $exception->getMessage());

            return;
        }

        $limits = [
            'book-ip:'.request()->ip() => (int) config('barbercuts.booking.max_per_ip_per_hour'),
            'book-phone:'.$phone => (int) config('barbercuts.booking.max_per_phone_per_hour'),
        ];

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $this->addError('booking', 'Je hebt kort na elkaar veel afspraken gemaakt. Probeer het later opnieuw of bel de zaak.');

                return;
            }
        }

        $user = Auth::user();

        try {
            $appointment = $appointments->book(new BookingData(
                shop: $shop,
                service: $service,
                staff: $this->staff(),
                startsAt: $start,
                customer: new CustomerInput($this->name, $phone, $this->email ?: null),
                notes: $this->notes,
            ), $user instanceof User ? $user : null);
        } catch (BookingException $exception) {
            $this->addError('booking', $exception->getMessage());

            // Tijdstip net weg: terug naar de tijden, die zijn opnieuw berekend.
            if (! in_array($this->time, $this->pickerTimes(), true)) {
                $this->time = '';
                $this->step = 'time';
            }

            return;
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 3600);
        }

        if ($user instanceof User && $user->phone === null) {
            $user->forceFill(['phone' => $phone])->save();
        }

        session()->flash('booked', $appointment->manage_token);

        $this->redirect($appointment->manageUrl());
    }

    public function render(): View
    {
        $shop = $this->slotShop();
        $service = $this->service();
        $staff = $this->staff();
        $settings = $this->settings();
        $days = $this->step === 'time' ? $this->pickerDays() : [];

        return view('livewire.shop.book', [
            'shop' => $shop,
            'service' => $service,
            'staff' => $staff,
            'services' => $this->step === 'service' ? Service::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get() : collect(),
            'staffOptions' => $this->step === 'staff' && $service ? $this->eligibleStaff($service) : collect(),
            'days' => $days,
            'times' => $this->step === 'time' ? $this->pickerSlots() : [],
            'canLoadMore' => $this->daysShown < $this->maxDays(),
            'start' => $this->chosenStart()?->setTimezone($shop->timezone),
            'guestAllowed' => $settings->allow_guest_booking,
            'user' => Auth::user(),
        ])->layout('layouts::shop', ['shop' => $shop, 'title' => 'Afspraak maken bij '.$shop->name]);
    }
}
