<?php

namespace App\Filament\Widgets;

use App\Models\Barbershop;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class SignupsChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Nieuwe aanmeldingen per week';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $start = CarbonImmutable::now('Europe/Amsterdam')->startOfWeek()->subWeeks(11);
        $dates = Barbershop::query()->where('created_at', '>=', $start->utc())->pluck('created_at');

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
