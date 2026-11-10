<?php

namespace App\Livewire\Concerns;

use App\Support\WeekSchedule;

/**
 * Formulierstaat en acties voor <x-ui.week-hours>: weekrooster met pauzes.
 */
trait EditsWeekSchedule
{
    /**
     * @var array<int|string, mixed>
     */
    public array $week = [];

    /**
     * @var array<int, string>
     */
    public array $weekErrors = [];

    /**
     * Voeg een pauze toe: splits één doorlopend blok rond de lunch, of voeg een blok toe na het laatste.
     */
    public function addBlock(int $day): void
    {
        if (! isset($this->week[$day])) {
            return;
        }

        $blocks = array_values($this->week[$day]['blocks'] ?? []);
        $last = end($blocks) ?: ['from' => '09:00', 'to' => '18:00'];

        if (count($blocks) === 1 && $last['from'] < '12:30' && $last['to'] > '13:00') {
            $blocks = [
                ['from' => $last['from'], 'to' => '12:30'],
                ['from' => '13:00', 'to' => $last['to']],
            ];
        } elseif (count($blocks) < 4 && WeekSchedule::isTime((string) $last['to']) && $last['to'] < '22:00') {
            $start = (int) substr($last['to'], 0, 2) * 60 + (int) substr($last['to'], 3, 2) + 30;
            $end = min($start + 180, 23 * 60 + 45);
            $blocks[] = ['from' => sprintf('%02d:%02d', intdiv($start, 60), $start % 60), 'to' => sprintf('%02d:%02d', intdiv($end, 60), $end % 60)];
        }

        $this->week[$day]['blocks'] = $blocks;
    }

    public function removeBlock(int $day, int $index): void
    {
        $blocks = array_values($this->week[$day]['blocks'] ?? []);

        if (count($blocks) <= 1) {
            return;
        }

        unset($blocks[$index]);
        $this->week[$day]['blocks'] = array_values($blocks);
    }

    public function copyDayToAll(int $day): void
    {
        if (! isset($this->week[$day])) {
            return;
        }

        foreach (array_keys(WeekSchedule::DAY_NAMES) as $other) {
            if ($this->week[$other]['open'] ?? false) {
                $this->week[$other]['blocks'] = $this->week[$day]['blocks'];
            }
        }

        $this->dispatch('toast', message: 'Gekopieerd naar alle open dagen');
    }

    protected function weekIsValid(): bool
    {
        $this->weekErrors = WeekSchedule::errors($this->week);

        return $this->weekErrors === [];
    }
}
