<?php

namespace App\Filament\Widgets\Insights;

use App\Services\AdminInsightsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Twee lijstjes naast elkaar: populairste diensten en resultaten per pitcher.
 */
class RankingsWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.insights-rankings';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $days = AdminInsightsService::days($this->pageFilters['period'] ?? null);
        $insights = app(AdminInsightsService::class);

        return [
            'services' => $insights->topServices($days),
            'pitchers' => $insights->pitchers($days),
        ];
    }
}
