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
