<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Zet een Nederlands adres, postcode of plaats om naar coördinaten via de Locatieserver van PDOK (gratis, van de overheid).
 * Geen sleutel nodig. Uitkomsten worden bewaard, zodat dezelfde zoekvraag niet steeds opnieuw wordt opgevraagd.
 */
class Geocoder
{
    private const string ENDPOINT = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free';

    /**
     * @return array{lat: float, lng: float, label: string}|null
     */
    public function find(string $query): ?array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query) ?? '');
        // PDOK kent postcodes zonder spatie ("3511AB"); met spatie zoekt het op losse woorden en komt er iets anders uit.
        $query = preg_replace('/\b(\d{4}) ?([A-Za-z]{2})\b/', '$1$2', $query) ?? $query;

        if ($query === '' || mb_strlen($query) > 120 || ! config('barbercuts.geocoding.enabled')) {
            return null;
        }

        $key = 'geocode:'.sha1(mb_strtolower($query));
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached['lat'] === null ? null : $cached;
        }

        $result = $this->lookup($query);

        // Ook "niet gevonden" onthouden (korter), zodat een typfout PDOK niet blijft belasten.
        Cache::put($key, $result ?? ['lat' => null], $result === null ? now()->addHour() : now()->addDays(30));

        return $result;
    }

    /**
     * @return array{lat: float, lng: float, label: string}|null
     */
    private function lookup(string $query): ?array
    {
        try {
            $response = Http::timeout(4)->acceptJson()->get(self::ENDPOINT, [
                'q' => $query,
                'rows' => 1,
                'fl' => 'weergavenaam,centroide_ll,type',
                'fq' => 'type:(adres OR postcode OR woonplaats OR weg OR gemeente)',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $doc = $response->successful() ? $response->json('response.docs.0') : null;

        // centroide_ll is "POINT(lengte breedte)"
        if (! is_array($doc) || preg_match('/POINT\(([-\d.]+) ([-\d.]+)\)/', (string) ($doc['centroide_ll'] ?? ''), $match) !== 1) {
            return null;
        }

        return ['lat' => (float) $match[2], 'lng' => (float) $match[1], 'label' => (string) ($doc['weergavenaam'] ?? $query)];
    }
}
