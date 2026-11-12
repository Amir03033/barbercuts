<?php

namespace App\Livewire;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Support\Features;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Klantaccount: eigen afspraken bij alle zaken (§9.3). Met notificatievoorkeuren (fase 5).
 *
 * Geen tenant: er wordt expliciet gefilterd op de klantrecords van de ingelogde gebruiker (§6.3).
 */
#[Layout('layouts::platform')]
#[Title('Mijn afspraken')]
class Account extends Component
{
    public bool $preferPush = true;

    public bool $preferMail = true;

    public bool $preferSms = false;

    public bool $preferWhatsapp = false;

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->preferPush = $user->prefersChannel('push');
        $this->preferMail = $user->prefersChannel('mail');
        $this->preferSms = $user->prefersChannel('sms');
        $this->preferWhatsapp = $user->prefersChannel('whatsapp');
    }

    public function savePreferences(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $user->update(['notification_preferences' => [
            'push' => $this->preferPush,
            'mail' => $this->preferMail,
            'sms' => $this->preferSms,
            'whatsapp' => $this->preferWhatsapp,
        ]]);

        $this->dispatch('toast', message: 'Voorkeuren opgeslagen');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $appointments = Appointment::withoutGlobalScopes()
            ->whereIn('customer_id', $user->customers()->select('id'))
            ->with(['barbershop', 'staffMember'])
            ->orderBy('starts_at')
            ->get();

        [$upcoming, $past] = $appointments->partition(
            fn (Appointment $appointment): bool => $appointment->status === AppointmentStatus::Scheduled && $appointment->ends_at->isFuture()
        );

        return view('livewire.account', [
            'user' => $user,
            'loyalty' => $user->customers()->where('loyalty_stamps', '>', 0)->with('barbershop')->get()
                ->map(fn (Customer $customer): array => ['customer' => $customer, 'card' => app(LoyaltyService::class)->cardFor($customer->barbershop_id)])
                ->filter(fn (array $row): bool => $row['card'] !== null)->values(),
            'upcoming' => $upcoming->values(),
            'past' => $past->sortByDesc('starts_at')->take(30)->values(),
            'smsAvailable' => Features::sms(),
            'whatsappAvailable' => Features::whatsapp(),
        ]);
    }
}
