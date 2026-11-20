<?php

namespace App\Filament\Widgets\Insights;

use App\Services\AdminInsightsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Omzet die de zaken via het platform verwerkten, per dag (per week bij een jaar).
 */
class RevenueChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Omzet verwerkt';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $series = app(AdminInsightsService::class)->revenueSeries(AdminInsightsService::days($this->pageFilters['period'] ?? null));

        return [
            'datasets' => [[
                'label' => 'Omzet (€)',
                'data' => array_map(fn (int $cents): float => round($cents / 100, 2), array_values($series)),
                'borderColor' => '#0071e3',
                'backgroundColor' => 'rgba(0, 113, 227, 0.08)',
                'fill' => true,
                'tension' => 0.3,
                // Geen uitschieters onder nul tussen twee punten
                'cubicInterpolationMode' => 'monotone',
                'pointRadius' => 0,
            ]],
            'labels' => array_keys($series),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true], 'x' => ['grid' => ['display' => false]]],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
