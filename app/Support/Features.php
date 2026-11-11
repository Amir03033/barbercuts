<?php

namespace App\Support;

/**
 * Feature flags. De waarde uit /admin (platform_settings) gaat voor die uit .env/config (§11).
 */
class Features
{
    public static function billing(): bool
    {
        return (bool) PlatformSettings::get('features.billing', config('barbercuts.billing.enabled'));
    }

    public static function deposits(): bool
    {
        return (bool) PlatformSettings::get('features.deposits', config('barbercuts.features.deposits'));
    }

    public static function sms(): bool
    {
        return (bool) PlatformSettings::get('features.sms', config('barbercuts.features.sms', false));
    }

    public static function whatsapp(): bool
    {
        return (bool) PlatformSettings::get('features.whatsapp', config('barbercuts.features.whatsapp', false));
    }

    /**
     * Gereserveerde slugs: de vaste lijst uit config plus wat de admin erbij zet.
     *
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        $extra = array_filter(array_map('strval', (array) PlatformSettings::get('reserved_slugs_extra', [])));

        return array_values(array_unique([...config('barbercuts.reserved_slugs'), ...$extra]));
    }
}
