<?php

namespace App\Policies;

use App\Models\Barbershop;
use App\Models\StaffMember;
use App\Models\User;

class StaffMemberPolicy
{
    public function view(User $user, StaffMember $staffMember): bool
    {
        return $user->isMemberOf($this->shop($staffMember));
    }

    /**
     * Naam, kleur, actief, uitnodigen, verwijderen: alleen de eigenaar.
     */
    public function update(User $user, StaffMember $staffMember): bool
    {
        return $user->isOwnerOf($this->shop($staffMember));
    }

    public function delete(User $user, StaffMember $staffMember): bool
    {
        return $this->update($user, $staffMember);
    }

    /**
     * Werktijden en vrije dagen: de eigenaar, of de kapper zelf voor zijn eigen agenda.
     */
    public function updateSchedule(User $user, StaffMember $staffMember): bool
    {
        $shop = $this->shop($staffMember);

        return $user->isOwnerOf($shop)
            || ($user->isMemberOf($shop) && $staffMember->user_id === $user->id);
    }

    private function shop(StaffMember $staffMember): Barbershop
    {
        return Barbershop::findOrFail($staffMember->barbershop_id);
    }
}
