<?php

namespace App\Filament\Widgets;

use App\Services\PlatformStatsService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class AppointmentsChartWidget extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Afspraken per dag (laatste 30 dagen)';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $stats = app(PlatformStatsService::class);
        $stats->ensureToday();

        $created = $stats->series('appointments_created', 30);

        return [
            'datasets' => [
                ['label' => 'Gemaakt', 'data' => array_values($created), 'borderColor' => '#2563eb'],
                ['label' => 'Uitgevoerd', 'data' => array_values($stats->series('appointments_done', 30)), 'borderColor' => '#16a34a'],
            ],
            'labels' => array_map(fn (string $date): string => CarbonImmutable::parse($date)->format('d-m'), array_keys($created)),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
