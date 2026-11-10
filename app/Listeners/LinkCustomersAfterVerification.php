<?php

namespace App\Listeners;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

/**
 * Koppelt klantrecords bij andere zaken aan het account zodra het e-mailadres bevestigd is (§6.5).
 * Pas na bevestiging, zodat niemand met andermans e-mailadres diens afspraken kan inzien.
 */
class LinkCustomersAfterVerification
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $linkedShops = $user->customers()->pluck('barbershop_id')->all();

        Customer::withoutGlobalScopes()
            ->whereNull('user_id')
            ->where('email', $user->email)
            ->whereNotIn('barbershop_id', $linkedShops)
            ->orderBy('id')
            ->get()
            ->unique('barbershop_id')
            ->each(function (Customer $customer) use ($user): void {
                $customer->user_id = $user->id;
                $customer->save();
            });
    }
}
