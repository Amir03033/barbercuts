<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Facturering (B12)
    |--------------------------------------------------------------------------
    | Zolang dit uit staat is het platform gratis en zijn er geen betaalschermen.
    */
    'billing' => [
        'enabled' => (bool) env('BILLING_ENABLED', false),
        // Tot de plans-tabel er is (fase 4) staat de proefperiode hier.
        'trial_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'deposits' => (bool) env('FEATURE_DEPOSITS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting op online boeken (fase 2)
    |--------------------------------------------------------------------------
    */
    'booking' => [
        'max_per_ip_per_hour' => (int) env('BOOKING_MAX_PER_IP_PER_HOUR', 10),
        'max_per_phone_per_hour' => (int) env('BOOKING_MAX_PER_PHONE_PER_HOUR', 5),
    ],

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gereserveerde slugs (§4)
    |--------------------------------------------------------------------------
    | Deze namen mogen niet als slug van een zaak gebruikt worden.
    */
    'reserved_slugs' => [
        'app', 'admin', 'account', 'afspraak', 'aanmelden', 'inloggen', 'registreren', 'uitloggen',
        'wachtwoord-vergeten', 'wachtwoord-herstellen', 'e-mail-bevestigen', 'uitnodiging', 'prijzen', 'privacy',
        'voorwaarden', 'contact', 'over', 'help', 'api', 'up', 'webhooks', 'storage', 'build',
        'livewire', 'filament', 'css', 'js', 'images', 'kappers', 'zoeken', '_ui',
    ],

];
