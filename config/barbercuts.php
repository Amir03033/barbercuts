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
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'deposits' => (bool) env('FEATURE_DEPOSITS', false),
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
        'wachtwoord-vergeten', 'wachtwoord-herstellen', 'e-mail-bevestigen', 'prijzen', 'privacy',
        'voorwaarden', 'contact', 'over', 'help', 'api', 'up', 'webhooks', 'storage', 'build',
        'livewire', 'filament', 'css', 'js', 'images', 'kappers', 'zoeken', '_ui',
    ],

];
