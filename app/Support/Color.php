<?php

namespace App\Support;

/**
 * Kleurhulpjes voor het thema van een zaak (§7.9).
 */
class Color
{
    public const string LIGHT_BACKGROUND = '#ffffff';

    public const string DARK_BACKGROUND = '#0f0f12';

    /** Voorgestelde accentkleuren in de instellingen. */
    public const array PRESETS = [
        '#111827', '#2563eb', '#0d9488', '#16a34a', '#d97706', '#dc2626', '#db2777', '#7c3aed',
    ];

    /** Kleuren voor kappers in de agenda. */
    public const array STAFF_COLORS = [
        '#2563eb', '#16a34a', '#d97706', '#dc2626', '#7c3aed', '#0d9488', '#db2777', '#475569',
    ];

    public static function isHex(?string $color): bool
    {
        return $color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1;
    }

    /**
     * Relatieve luminantie volgens WCAG 2.
     */
    /**
     * De kleur als rood, groen en blauw (0–255).
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    public static function luminance(string $hex): float
    {
        $channels = array_map(
            function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($hex, '#'), 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * Contrastverhouding tussen twee kleuren (1 t/m 21).
     */
    public static function contrast(string $first, string $second): float
    {
        $lighter = max(self::luminance($first), self::luminance($second));
        $darker = min(self::luminance($first), self::luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Wit of zwart: wat het best leesbaar is op deze kleur.
     */
    public static function onColor(string $hex): string
    {
        return self::contrast($hex, '#ffffff') >= self::contrast($hex, '#111114') ? '#ffffff' : '#111114';
    }
}
