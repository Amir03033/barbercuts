<?php

namespace App\Livewire\Concerns;

use App\Models\Barbershop;
use App\Models\User;
use App\Support\CurrentShop;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

/**
 * Voor Livewire-componenten in /app: de huidige zaak en de rechten van de ingelogde gebruiker.
 */
trait InShop
{
    use AuthorizesRequests;

    protected function shop(): Barbershop
    {
        $shop = app(CurrentShop::class)->get();

        abort_if($shop === null, 404);

        return $shop;
    }

    protected function user(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    protected function isOwner(): bool
    {
        return $this->user()->isOwnerOf($this->shop());
    }

    /**
     * Alleen de eigenaar: diensten, openingstijden, thema, instellingen en kappers (§6.2).
     */
    protected function authorizeOwner(): void
    {
        $this->authorize('manage', $this->shop());
    }
}
