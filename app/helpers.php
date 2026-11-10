<?php

use Carbon\CarbonInterface;

if (! function_exists('money')) {
    /**
     * Formatteer een bedrag in centen volgens de Nederlandse notatie: € 1.234,50.
     */
    function money(int $cents): string
    {
        $prefix = $cents < 0 ? '-' : '';

        return $prefix.'€ '.number_format(abs($cents) / 100, 2, ',', '.');
    }
}

if (! function_exists('human_date')) {
    /**
     * Menselijke datum: "Vandaag", "Morgen", "Gisteren" of "do 14 nov".
     */
    function human_date(CarbonInterface $date, ?string $timezone = null): string
    {
        $timezone ??= 'Europe/Amsterdam';
        $local = $date->copy()->setTimezone($timezone)->locale('nl');
        $today = now($timezone)->startOfDay();
        $diff = (int) $today->diffInDays($local->copy()->startOfDay(), false);

        return match ($diff) {
            0 => 'Vandaag',
            1 => 'Morgen',
            -1 => 'Gisteren',
            default => rtrim($local->isoFormat('dd D MMM'), '.'),
        };
    }
}

if (! function_exists('human_datetime')) {
    /**
     * Menselijke datum met tijd: "Vandaag 14:30", "do 14 nov 09:00".
     */
    function human_datetime(CarbonInterface $date, ?string $timezone = null): string
    {
        $timezone ??= 'Europe/Amsterdam';

        return human_date($date, $timezone).' '.$date->copy()->setTimezone($timezone)->format('H:i');
    }
}

if (! function_exists('parse_money')) {
    /**
     * Zet invoer als "25", "25,5" of "1.234,50" om naar centen. Ongeldig of negatief = null.
     */
    function parse_money(?string $input): ?int
    {
        $value = str_replace(['€', ' ', "\u{00A0}"], '', trim((string) $input));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }
}

if (! function_exists('money_input')) {
    /**
     * Centen als invoerwaarde: 2500 → "25,00".
     */
    function money_input(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, ',', '');
    }
}

if (! function_exists('duration_label')) {
    /**
     * Minuten als leesbare duur: 30 → "30 min", 90 → "1 u 30 min", 1440 → "24 uur".
     */
    function duration_label(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? $hours.' uur' : $hours.' u '.$rest.' min';
    }
}

if (! function_exists('nl_date')) {
    /**
     * Datum in het Nederlands zonder afkortingspunten: "ma 20 jul 2026" in plaats van "ma 20 jul. 2026".
     */
    function nl_date(CarbonInterface $date, string $isoFormat = 'dd D MMM'): string
    {
        return str_replace('.', '', $date->copy()->locale('nl')->isoFormat($isoFormat));
    }
}
