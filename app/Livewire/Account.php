<?php

namespace App\Livewire;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Klantaccount: eigen afspraken bij alle zaken (§9.3). Profiel en notificatievoorkeuren volgen in fase 5.
 *
 * Geen tenant: er wordt expliciet gefilterd op de klantrecords van de ingelogde gebruiker (§6.3).
 */
#[Layout('layouts::platform')]
#[Title('Mijn afspraken')]
class Account extends Component
{
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
            'upcoming' => $upcoming->values(),
            'past' => $past->sortByDesc('starts_at')->take(30)->values(),
        ]);
    }
}
