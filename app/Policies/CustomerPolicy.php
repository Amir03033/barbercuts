<?php

namespace App\Policies;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Klanten zien en bewerken: eigenaar en kappers van de zaak (§6.2).
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->isMemberOf(Barbershop::findOrFail($customer->barbershop_id));
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->isOwnerOf(Barbershop::findOrFail($customer->barbershop_id));
    }
}
