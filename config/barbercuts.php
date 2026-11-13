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
        'sms' => (bool) env('FEATURE_SMS', false),
        'whatsapp' => (bool) env('FEATURE_WHATSAPP', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bedrijfsgegevens (privacyverklaring, voorwaarden, contact)
    |--------------------------------------------------------------------------
    | Vul deze in via .env; lege velden worden op de pagina's weggelaten.
    */
    'company' => [
        'name' => env('COMPANY_NAME', 'BarberCuts'),
        // De onderneming achter BarberCuts, zoals die in de juridische teksten staat.
        'legal_name' => env('COMPANY_LEGAL_NAME', 'Jebbari'),
        'kvk' => env('COMPANY_KVK', '93721382'),
        'vat' => env('COMPANY_VAT'),
        'address' => env('COMPANY_ADDRESS'),
        'email' => env('COMPANY_EMAIL', 'hallo@barbercuts.nl'),
        'phone' => env('COMPANY_PHONE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maker van het platform: staat in elke voettekst ("Developed by …")
    |--------------------------------------------------------------------------
    */
    'developer' => [
        'name' => env('DEVELOPER_NAME', 'Jebbari'),
        'kvk' => env('DEVELOPER_KVK', '93721382'),
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

    /*
    |--------------------------------------------------------------------------
    | SMS en WhatsApp (fase 5): provider 'log' (standaard) of 'twilio'; kosten per bericht in eurocenten
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'provider' => env('SMS_PROVIDER', 'log'),
        'cost_cents' => (int) env('SMS_COST_CENTS', 8),
    ],

    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'log'),
        'cost_cents' => (int) env('WHATSAPP_COST_CENTS', 6),
        // Content SID van de door Meta goedgekeurde template, per berichtsoort (alleen nodig met provider twilio).
        'templates' => [
            'confirmation' => env('WHATSAPP_TEMPLATE_CONFIRMATION'),
            'reminder_1' => env('WHATSAPP_TEMPLATE_REMINDER'),
            'reminder_2' => env('WHATSAPP_TEMPLATE_REMINDER'),
            'rescheduled' => env('WHATSAPP_TEMPLATE_RESCHEDULED'),
            'cancelled' => env('WHATSAPP_TEMPLATE_CANCELLED'),
            'review_request' => env('WHATSAPP_TEMPLATE_REVIEW_REQUEST'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo voor pitchers (fase 6): hoe lang een demozaak blijft bestaan
    |--------------------------------------------------------------------------
    */
    'geocoding' => [
        // Adressen omzetten naar coördinaten via PDOK (fase 7). Staat uit in tests.
        'enabled' => (bool) env('GEOCODING_ENABLED', true),
    ],

    'demo' => [
        'hours' => (int) env('DEMO_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gereserveerde slugs (§4)
    |--------------------------------------------------------------------------
    | Deze namen mogen niet als slug van een zaak gebruikt worden.
    */
    'reserved_slugs' => [
        'app', 'admin', 'account', 'push', 'afspraak', 'aanmelden', 'inloggen', 'registreren', 'uitloggen',
        'wachtwoord-vergeten', 'wachtwoord-herstellen', 'e-mail-bevestigen', 'uitnodiging', 'prijzen', 'privacy',
        'voorwaarden', 'verwerkersovereenkomst', 'contact', 'impersonate', 'over', 'help', 'api', 'up', 'webhooks', 'storage', 'build',
        'livewire', 'filament', 'css', 'js', 'images', 'kappers', 'zoeken', '_ui', 'demo', 'wachtlijst', 'afmelden', 'wissel-zaak',
    ],

];
