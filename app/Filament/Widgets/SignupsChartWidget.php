<?php

namespace App\Filament\Widgets;

use App\Models\Barbershop;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class SignupsChartWidget extends ChartWidget
{
    /** Niet elke 5 seconden opnieuw laden (standaard in Filament): dat maakte het dashboard traag bij scrollen. */
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Nieuwe aanmeldingen per week';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $start = CarbonImmutable::now('Europe/Amsterdam')->startOfWeek()->subWeeks(11);
        $dates = Barbershop::query()->real()->where('created_at', '>=', $start->utc())->pluck('created_at');

        $labels = [];
        $values = [];

        for ($week = 0; $week < 12; $week++) {
            $from = $start->addWeeks($week);
            $to = $from->addWeek();
            $labels[] = $from->format('d-m');
            $values[] = $dates->filter(fn ($created): bool => $created >= $from && $created < $to)->count();
        }

        return [
            'datasets' => [['label' => 'Aanmeldingen', 'data' => $values]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
