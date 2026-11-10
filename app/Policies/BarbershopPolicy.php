<?php

namespace App\Policies;

use App\Models\Barbershop;
use App\Models\User;

class BarbershopPolicy
{
    /**
     * Mag in /app van deze zaak: eigenaar en kappers.
     */
    public function view(User $user, Barbershop $shop): bool
    {
        return $user->isMemberOf($shop);
    }

    /**
     * Diensten, openingstijden, thema, instellingen, kappers en abonnement: alleen de eigenaar (§6.2).
     */
    public function manage(User $user, Barbershop $shop): bool
    {
        return $user->isOwnerOf($shop);
    }
}
