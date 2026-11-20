<?php

namespace App\Support;

/**
 * Hoe groot een upload op deze server mag zijn: het kleinste van upload_max_filesize en post_max_size in php.ini.
 * Zo kan een uploadveld de echte grens tonen, in plaats van een vage "fout tijdens upload" als PHP het bestand weigert.
 */
class UploadLimit
{
    /**
     * De grens in kilobytes (0 = geen grens ingesteld).
     */
    public static function kilobytes(): int
    {
        $limits = array_filter([
            self::toKilobytes((string) ini_get('upload_max_filesize')),
            self::toKilobytes((string) ini_get('post_max_size')),
        ], fn (int $kilobytes): bool => $kilobytes > 0);

        return $limits === [] ? 0 : min($limits);
    }

    public static function label(int $kilobytes): string
    {
        return $kilobytes >= 1024 ? round($kilobytes / 1024).' MB' : $kilobytes.' KB';
    }

    public static function toKilobytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1' || $value === '0') {
            return 0;
        }

        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024,
            'm' => $number * 1024,
            'k' => $number,
            default => $number / 1024,
        };
    }
}
