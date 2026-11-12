<?php

namespace App\Jobs;

use App\Services\Geo\Geocoder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Haalt de coördinaten van een zaak op na een adreswijziging, voor "kappers in de buurt" (fase 7).
 */
class GeocodeBarbershop implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $shopId) {}

    public function handle(Geocoder $geocoder): void
    {
        if (! config('barbercuts.geocoding.enabled')) {
            return;
        }

        $shop = DB::table('barbershops')->where('id', $this->shopId)->first(['address', 'postal_code', 'city']);

        if ($shop === null) {
            return;
        }

        $query = collect([$shop->address, $shop->postal_code, $shop->city])->filter()->implode(' ');
        $found = $query === '' ? null : $geocoder->find($query);

        // Rechtstreeks in de tabel: geen modelevents, dus geen nieuwe ronde en geen alleen-lezen-controle.
        DB::table('barbershops')->where('id', $this->shopId)->update([
            'latitude' => $found['lat'] ?? null,
            'longitude' => $found['lng'] ?? null,
        ]);
    }
}
