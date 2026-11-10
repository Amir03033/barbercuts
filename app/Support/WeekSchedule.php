<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Weekrooster als formulierstaat: per weekdag (1 = ma … 7 = zo) aan/uit en één of meer blokken.
 * Meerdere blokken op een dag = pauze ertussen. Gebruikt voor openingstijden én werktijden.
 *
 * @phpstan-type Block array{from: string, to: string}
 * @phpstan-type Day array{open: bool, blocks: list<Block>}
 * @phpstan-type Week array<int, Day>
 */
class WeekSchedule
{
    public const array DAY_NAMES = [
        1 => 'Maandag', 2 => 'Dinsdag', 3 => 'Woensdag', 4 => 'Donderdag', 5 => 'Vrijdag', 6 => 'Zaterdag', 7 => 'Zondag',
    ];

    /**
     * Preset voor nieuwe zaken: di–za 09:00–18:00 (§8).
     *
     * @return Week
     */
    public static function preset(): array
    {
        $week = [];

        foreach (array_keys(self::DAY_NAMES) as $day) {
            $week[$day] = [
                'open' => $day >= 2 && $day <= 6,
                'blocks' => [['from' => '09:00', 'to' => '18:00']],
            ];
        }

        return $week;
    }

    /**
     * Bouw de formulierstaat op uit rijen uit de database.
     *
     * @param  Collection<int, covariant object>  $rows
     * @return Week
     */
    public static function fromRows(Collection $rows, string $fromColumn, string $toColumn): array
    {
        $week = [];

        foreach (array_keys(self::DAY_NAMES) as $day) {
            $blocks = $rows->where('day_of_week', $day)
                ->sortBy($fromColumn)
                ->map(fn (object $row): array => [
                    'from' => substr((string) $row->{$fromColumn}, 0, 5),
                    'to' => substr((string) $row->{$toColumn}, 0, 5),
                ])
                ->values()
                ->all();

            $week[$day] = [
                'open' => $blocks !== [],
                'blocks' => $blocks !== [] ? $blocks : [['from' => '09:00', 'to' => '18:00']],
            ];
        }

        return $week;
    }

    /**
     * Controleer het rooster. Geeft foutmeldingen per dag terug; leeg = in orde.
     *
     * @param  array<int|string, mixed>  $week
     * @return array<int, string>
     */
    public static function errors(array $week): array
    {
        $errors = [];

        foreach (array_keys(self::DAY_NAMES) as $day) {
            $state = $week[$day] ?? null;

            if (! is_array($state) || ! ($state['open'] ?? false)) {
                continue;
            }

            $blocks = array_values((array) ($state['blocks'] ?? []));

            if ($blocks === []) {
                $errors[$day] = 'Vul een begin- en eindtijd in.';

                continue;
            }

            $previousEnd = null;

            foreach ($blocks as $block) {
                $from = (string) ($block['from'] ?? '');
                $to = (string) ($block['to'] ?? '');

                if (! self::isTime($from) || ! self::isTime($to)) {
                    $errors[$day] = 'Vul een begin- en eindtijd in.';

                    continue 2;
                }

                if ($from >= $to) {
                    $errors[$day] = 'De eindtijd moet na de begintijd liggen.';

                    continue 2;
                }

                if ($previousEnd !== null && $from < $previousEnd) {
                    $errors[$day] = 'De blokken overlappen elkaar.';

                    continue 2;
                }

                $previousEnd = $to;
            }
        }

        return $errors;
    }

    /**
     * Zet de formulierstaat om naar rijen voor de database.
     *
     * @param  array<int|string, mixed>  $week
     * @return list<array<string, int|string>>
     */
    public static function toRows(array $week, string $fromColumn, string $toColumn): array
    {
        $rows = [];

        foreach (array_keys(self::DAY_NAMES) as $day) {
            if (! ($week[$day]['open'] ?? false)) {
                continue;
            }

            foreach ((array) $week[$day]['blocks'] as $block) {
                $rows[] = ['day_of_week' => $day, $fromColumn => $block['from'].':00', $toColumn => $block['to'].':00'];
            }
        }

        return $rows;
    }

    public static function isTime(string $value): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }
}
