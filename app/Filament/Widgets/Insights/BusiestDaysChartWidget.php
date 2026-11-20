<?php

namespace App\Filament\Widgets\Insights;

use App\Services\AdminInsightsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Drukste dagen van de week, over alle zaken.
 */
class BusiestDaysChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Drukste dagen';

    protected function getData(): array
    {
        $weekdays = app(AdminInsightsService::class)->busiest(AdminInsightsService::days($this->pageFilters['period'] ?? null))['weekdays'];

        return [
            'datasets' => [['label' => 'Afspraken', 'data' => array_values($weekdays), 'backgroundColor' => '#34c759', 'borderRadius' => 6]],
            'labels' => ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'],
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
