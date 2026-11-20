<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Insights\BookingStatsWidget;
use App\Filament\Widgets\Insights\BusiestDaysChartWidget;
use App\Filament\Widgets\Insights\BusiestHoursChartWidget;
use App\Filament\Widgets\Insights\CustomerStatsWidget;
use App\Filament\Widgets\Insights\GrowthStatsWidget;
use App\Filament\Widgets\Insights\OnboardingFunnelChartWidget;
use App\Filament\Widgets\Insights\RankingsWidget;
use App\Filament\Widgets\Insights\RevenueChartWidget;
use App\Services\AdminInsightsService;
use BackedEnum;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Uitgebreide statistieken van het hele platform over een gekozen periode: groei en pitchers, afspraken, klanten,
 * omzet, drukste uren en dagen, waar zaken afhaken bij het aanmelden, populairste diensten.
 */
class Statistics extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'statistieken';

    protected static ?string $title = 'Statistieken';

    protected static ?string $navigationLabel = 'Statistieken';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 1;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Alle echte zaken samen, zonder demozaken. Cijfers worden elke vijf minuten ververst.';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            ToggleButtons::make('period')
                ->label('Periode')
                ->options(AdminInsightsService::PERIODS)
                ->default(30)
                ->inline()
                ->columnSpanFull(),
        ]);
    }

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 3];
    }

    /**
     * @return list<class-string>
     */
    public function getWidgets(): array
    {
        return [
            GrowthStatsWidget::class,
            BookingStatsWidget::class,
            RevenueChartWidget::class,
            BusiestHoursChartWidget::class,
            BusiestDaysChartWidget::class,
            OnboardingFunnelChartWidget::class,
            CustomerStatsWidget::class,
            RankingsWidget::class,
        ];
    }
}
