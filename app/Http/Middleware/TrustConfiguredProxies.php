<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Achter Plesk/nginx komt het echte IP-adres van de bezoeker in X-Forwarded-For. Zonder vertrouwen in die proxy ziet de app
 * alle bezoekers als hetzelfde IP en delen ze de limieten op inloggen, registreren en boeken.
 *
 * Instelling: TRUSTED_PROXIES in .env (leeg = niemand, * = alle, of IP-adressen gescheiden door komma's). We lezen dit pas per
 * verzoek uit de config: met een gecachte config wordt .env niet meer gelezen en is env() hier leeg.
 */
class TrustConfiguredProxies extends TrustProxies
{
    /**
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        $configured = trim((string) config('barbercuts.trusted_proxies'));

        if ($configured === '') {
            return null;
        }

        return $configured === '*' ? '*' : array_map('trim', explode(',', $configured));
    }
}
