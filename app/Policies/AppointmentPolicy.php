<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\StaffMember;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Agenda's van alle kappers zien: eigenaar en kappers (collega's alleen-lezen).
     */
    public function view(User $user, Appointment $appointment): bool
    {
        return $user->isMemberOf($this->shop($appointment));
    }

    /**
     * Verplaatsen, annuleren, voltooien, no-show: de eigenaar alles, een kapper alleen in de eigen agenda (§6.2).
     */
    public function update(User $user, Appointment $appointment): bool
    {
        $shop = $this->shop($appointment);

        if ($user->isOwnerOf($shop)) {
            return true;
        }

        if (! $user->isMemberOf($shop)) {
            return false;
        }

        return StaffMember::withoutGlobalScopes()
            ->whereKey($appointment->staff_member_id)
            ->where('barbershop_id', $shop->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function shop(Appointment $appointment): Barbershop
    {
        return Barbershop::findOrFail($appointment->barbershop_id);
    }
}
