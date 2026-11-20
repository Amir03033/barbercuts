<?php

namespace App\Filament\Widgets\Insights;

use App\Services\AdminInsightsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Waar zaken afhaken tijdens het aanmelden (zaken die in de periode begonnen).
 */
class OnboardingFunnelChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Waar haken zaken af?';

    protected function getData(): array
    {
        $funnel = app(AdminInsightsService::class)->onboardingFunnel(AdminInsightsService::days($this->pageFilters['period'] ?? null));

        return [
            'datasets' => [['label' => 'Zaken', 'data' => array_values($funnel), 'backgroundColor' => ['#ff9f0a', '#ff9f0a', '#ff9f0a', '#34c759'], 'borderRadius' => 6]],
            // Zaken die in deze periode begonnen met aanmelden, per stap waar ze nu staan.
            'labels' => ['Kappers', 'Tijden', 'Diensten', 'Online'],
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
