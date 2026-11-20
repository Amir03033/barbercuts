<?php

namespace App\Filament\Widgets\Insights;

use App\Services\AdminInsightsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Drukste uren van de dag (Nederlandse tijd), over alle zaken.
 */
class BusiestHoursChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Drukste uren';

    protected function getData(): array
    {
        $hours = app(AdminInsightsService::class)->busiest(AdminInsightsService::days($this->pageFilters['period'] ?? null))['hours'];

        return [
            'datasets' => [['label' => 'Afspraken', 'data' => array_values($hours), 'backgroundColor' => '#0071e3', 'borderRadius' => 6]],
            'labels' => array_map(fn (int $hour): string => sprintf('%02d:00', $hour), array_keys($hours)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        // Hoger dan de standaard (2:1): in een derde van de breedte werd de grafiek anders plat.
        return [
            'aspectRatio' => 1.4,
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]], 'x' => ['grid' => ['display' => false]]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
