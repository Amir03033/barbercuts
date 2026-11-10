<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Een periode voor statistieken, in de tijdzone van de zaak (§7.5). Het einde is exclusief.
 */
final readonly class Period
{
    public const array TYPES = ['day', 'week', 'month', 'year', 'custom'];

    private function __construct(
        public string $type,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    /**
     * @param  string  $anchor  een datum (Y-m-d) binnen de periode
     */
    public static function make(string $type, string $anchor, string $timezone, ?string $customTo = null): self
    {
        $date = CarbonImmutable::parse($anchor, $timezone)->startOfDay();

        return match ($type) {
            'week' => new self('week', $date->startOfWeek(CarbonImmutable::MONDAY), $date->startOfWeek(CarbonImmutable::MONDAY)->addWeek()),
            'month' => new self('month', $date->startOfMonth(), $date->startOfMonth()->addMonth()),
            'year' => new self('year', $date->startOfYear(), $date->startOfYear()->addYear()),
            'custom' => new self('custom', $date, max($date, CarbonImmutable::parse($customTo ?? $anchor, $timezone)->startOfDay())->addDay()),
            default => new self('day', $date, $date->addDay()),
        };
    }

    public static function month(string $yearMonth, string $timezone): self
    {
        return self::make('month', $yearMonth.'-01', $timezone);
    }

    /**
     * De periode direct hiervoor, even lang (of de vorige kalendermaand / het vorige jaar).
     */
    public function previous(): self
    {
        return match ($this->type) {
            'month' => new self('month', $this->from->subMonth(), $this->from),
            'year' => new self('year', $this->from->subYear(), $this->from),
            default => new self($this->type, $this->from->subDays($this->days()), $this->from),
        };
    }

    public function next(): self
    {
        return match ($this->type) {
            'month' => new self('month', $this->to, $this->to->addMonth()),
            'year' => new self('year', $this->to, $this->to->addYear()),
            default => new self($this->type, $this->to, $this->to->addDays($this->days())),
        };
    }

    public function days(): int
    {
        return (int) round($this->from->diffInDays($this->to));
    }

    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    /**
     * De laatste dag in de periode (inclusief).
     */
    public function toDate(): string
    {
        return $this->to->subDay()->toDateString();
    }

    public function label(): string
    {
        return match ($this->type) {
            'day' => ucfirst(human_date($this->from, $this->from->timezoneName)),
            'week' => 'Week '.$this->from->isoWeek.' · '.nl_date($this->from, 'D MMM').' – '.nl_date($this->to->subDay(), 'D MMM'),
            'month' => ucfirst(nl_date($this->from, 'MMMM YYYY')),
            'year' => (string) $this->from->year,
            default => nl_date($this->from, 'D MMM').' – '.nl_date($this->to->subDay(), 'D MMM YYYY'),
        };
    }

    public function comparisonLabel(): string
    {
        return match ($this->type) {
            'day' => 't.o.v. de dag ervoor',
            'week' => 't.o.v. vorige week',
            'month' => 't.o.v. vorige maand',
            'year' => 't.o.v. vorig jaar',
            default => 't.o.v. de periode ervoor',
        };
    }

    /**
     * Staven voor de grafiek: per uur (dag), per dag (week, maand, korte eigen periode) of per maand (jaar).
     *
     * @return list<array{label: string, from: CarbonImmutable, to: CarbonImmutable}>
     */
    public function buckets(): array
    {
        $buckets = [];

        if ($this->type === 'day') {
            foreach (range(7, 21) as $hour) {
                $start = $this->from->setTime($hour, 0);
                $buckets[] = ['label' => (string) $hour, 'from' => $start, 'to' => $start->addHour()];
            }

            return $buckets;
        }

        $perMonth = $this->type === 'year' || $this->days() > 62;

        for ($start = $this->from; $start < $this->to; $start = $end) {
            $end = $perMonth ? min($start->startOfMonth()->addMonth(), $this->to) : $start->addDay();

            $buckets[] = [
                'label' => match (true) {
                    $perMonth => mb_substr(nl_date($start, 'MMM'), 0, 1),
                    $this->type === 'week' => nl_date($start, 'dd'),
                    default => (string) $start->day,
                },
                'from' => $start,
                'to' => $end,
            ];
        }

        return $buckets;
    }
}
