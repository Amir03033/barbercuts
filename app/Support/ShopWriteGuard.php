<?php

namespace App\Support;

use App\Exceptions\ShopReadOnlyException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Voorkomt dat de eigenaar of een kapper in /app iets wijzigt zolang de zaak alleen-lezen is (§7.7).
 *
 * Het is bewust een controle op modelniveau: elke schrijfactie in /app gaat via een tenantmodel, dus dit
 * dekt alle schermen en Livewire-acties tegelijk. Klanten en gasten (die niet bij de zaak horen), de admin,
 * de console en de queue worden niet geblokkeerd; online boeken wordt apart afgewezen in AppointmentService.
 */
class ShopWriteGuard
{
    public static function assertWritable(): void
    {
        $shop = app(CurrentShop::class)->get();

        if ($shop === null || $shop->accessState()->isWritable()) {
            return;
        }

        $user = Auth::user();

        if ($user instanceof User && $user->isMemberOf($shop)) {
            throw new ShopReadOnlyException;
        }
    }
}
