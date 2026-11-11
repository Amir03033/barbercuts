<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Throwable;

/**
 * Platforminstellingen in de database (aan te passen in /admin). Ze overschrijven de standaard uit .env/config (§11).
 */
class PlatformSettings
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, self::all()) ? self::all()[$key] : $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function set(string $key, mixed $value): void
    {
        PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        self::forget();
    }

    public static function unset(string $key): void
    {
        PlatformSetting::query()->whereKey($key)->delete();
        self::forget();
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function all(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = PlatformSetting::query()->pluck('value', 'key')->all();
            } catch (Throwable) {
                // Tabel bestaat nog niet (bv. tijdens de eerste migratie): dan gelden de standaardwaarden.
                return [];
            }
        }

        return self::$cache;
    }
}
