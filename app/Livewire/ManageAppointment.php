<?php

namespace App\Livewire;

use App\Exceptions\BookingException;
use App\Livewire\Concerns\PicksSlot;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use App\Support\CurrentShop;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Afspraak bekijken, verplaatsen en annuleren via de beheerlink /afspraak/{token} (§9.2), ook voor gasten.
 * Direct na het boeken is dit ook het bevestigingsscherm.
 *
 * Deze pagina heeft geen tenant in de URL: de afspraak wordt expliciet op het token opgezocht en daarna
 * werkt de pagina binnen de zaak van die afspraak.
 */
class ManageAppointment extends Component
{
    use PicksSlot;

    #[Locked]
    public string $token = '';

    #[Locked]
    public bool $justBooked = false;

    public string $mode = 'view';

    public string $accountEmail = '';

    public string $accountPassword = '';

    private Appointment $appointment;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->load();

        $this->justBooked = session('booked') === $token;
        $this->accountEmail = (string) $this->appointment->customer->email;
    }

    public function hydrate(): void
    {
        $this->load();
    }

    private function load(): void
    {
        $this->appointment = Appointment::withoutGlobalScopes()
            ->with(['barbershop', 'customer', 'staffMember'])
            ->where('manage_token', $this->token)
            ->firstOrFail();

        app(CurrentShop::class)->set($this->appointment->barbershop);
    }

    protected function slotShop(): Barbershop
    {
        return $this->appointment->barbershop;
    }

    protected function slotService(): ?Service
    {
        // Reken met de duur van de afspraak zelf (snapshot).
        $service = clone Service::withoutGlobalScopes()->findOrFail($this->appointment->service_id);
        $service->duration = $this->appointment->duration;

        return $service;
    }

    protected function slotStaff(): ?StaffMember
    {
        return $this->appointment->staffMember;
    }

    protected function slotIgnore(): ?Appointment
    {
        return $this->appointment;
    }

    private function settings(): ShopSetting
    {
        return ShopSetting::query()->first() ?? new ShopSetting;
    }

    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function startReschedule(): void
    {
        $this->mode = 'reschedule';
        $this->date = $this->appointment->localStart()->toDateString();
        $this->time = '';
    }

    public function stopReschedule(): void
    {
        $this->mode = 'view';
        $this->resetErrorBag();
    }

    public function reschedule(AppointmentService $appointments): void
    {
        $start = $this->chosenStart();

        if ($start === null) {
            $this->addError('time', 'Kies een tijd.');

            return;
        }

        try {
            $appointments->reschedule($this->appointment, $start, null, $this->actor());
        } catch (BookingException $exception) {
            $this->addError('time', $exception->getMessage());
            $this->time = '';

            return;
        }

        $this->mode = 'view';
        $this->justBooked = false;
        $this->dispatch('toast', message: 'Je afspraak is verplaatst');
    }

    public function cancel(AppointmentService $appointments): void
    {
        try {
            $appointments->cancel($this->appointment, $this->actor());
        } catch (BookingException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');

            return;
        }

        $this->justBooked = false;
        $this->dispatch('close-sheet');
        $this->dispatch('toast', message: 'Je afspraak is geannuleerd');
    }

    /**
     * Gast maakt een account: deze afspraak (en latere) staan dan in /account (§6.5).
     */
    public function createAccount(): void
    {
        abort_if(Auth::check() || $this->appointment->customer->user_id !== null, 403);

        $this->accountEmail = strtolower(trim($this->accountEmail));

        $this->validate([
            'accountEmail' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'accountPassword' => ['required', 'string', Password::defaults()],
        ], messages: [
            'accountEmail.unique' => 'Er bestaat al een account met dit e-mailadres. Log in om deze afspraak te bewaren.',
        ], attributes: ['accountEmail' => 'e-mailadres', 'accountPassword' => 'wachtwoord']);

        $customer = $this->appointment->customer;

        $user = User::create([
            'name' => $customer->name,
            'email' => $this->accountEmail,
            'phone' => $customer->phone,
            'password' => $this->accountPassword,
        ]);

        $customer->user_id = $user->id;
        $customer->email = $customer->email ?: $this->accountEmail;
        $customer->save();

        event(new Registered($user));

        Auth::login($user, remember: true);
        Session::regenerate();

        $this->reset('accountPassword');
        $this->dispatch('toast', message: 'Je account is aangemaakt');
    }

    /**
     * Ingelogde klant bewaart deze afspraak in het eigen account.
     */
    public function saveToAccount(): void
    {
        $user = $this->actor();
        $customer = $this->appointment->customer;

        abort_if($user === null || $customer->user_id !== null, 403);

        if ($user->customers()->where('barbershop_id', $customer->barbershop_id)->exists()) {
            $this->dispatch('toast', message: 'Je hebt bij deze zaak al een klantprofiel in je account.', type: 'error');

            return;
        }

        $customer->user_id = $user->id;
        $customer->save();

        $this->dispatch('toast', message: 'Bewaard in je account');
    }

    public function render(): View
    {
        $appointment = $this->appointment;
        $shop = $appointment->barbershop;
        $settings = $this->settings();
        $user = $this->actor();
        $isStaff = $user?->isMemberOf($shop) ?? false;
        $scheduled = $appointment->isScheduled();
        $minutesLeft = now()->diffInMinutes($appointment->starts_at, false);
        $rescheduling = $this->mode === 'reschedule' && $scheduled;

        return view('livewire.manage-appointment', [
            'appointment' => $appointment,
            'shop' => $shop,
            'start' => $appointment->localStart(),
            'canReschedule' => $scheduled && ($isStaff || $minutesLeft >= $settings->customer_reschedule_limit),
            'canCancel' => $scheduled && ($isStaff || $minutesLeft >= $settings->customer_cancel_limit),
            'upcoming' => $scheduled && $minutesLeft > 0,
            'rescheduling' => $rescheduling,
            'days' => $rescheduling ? $this->pickerDays() : [],
            'times' => $rescheduling ? $this->pickerSlots() : [],
            'canLoadMore' => $this->daysShown < $this->maxDays(),
            'showCreateAccount' => $user === null && $appointment->customer->user_id === null,
            'showSaveToAccount' => $user !== null && ! $isStaff && $appointment->customer->user_id === null,
            'inAccount' => $user !== null && $appointment->customer->user_id === $user->id,
        ])->layout('layouts::shop', ['shop' => $shop, 'title' => 'Je afspraak bij '.$shop->name]);
    }
}
