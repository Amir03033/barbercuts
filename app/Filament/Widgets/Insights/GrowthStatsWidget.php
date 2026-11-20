<?php

namespace App\Filament\Widgets\Insights;

use App\Services\AdminInsightsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Groei en pitchers: aanmeldingen, afgerond, via pitchers, demo's en hoeveel demo's tot een aanmelding leidden.
 */
class GrowthStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Groei';

    protected function getStats(): array
    {
        $days = AdminInsightsService::days($this->pageFilters['period'] ?? null);
        $data = app(AdminInsightsService::class)->growth($days);
        $pct = fn (int $part, int $whole): string => $whole > 0 ? round($part / $whole * 100).'%' : '-';
        $change = $data['previous_signups'] > 0 ? (int) round(($data['signups'] - $data['previous_signups']) / $data['previous_signups'] * 100) : null;

        return [
            Stat::make('Nieuwe zaken', $data['signups'])
                ->description($change === null ? 'Vorige periode: '.$data['previous_signups'] : ($change >= 0 ? '+' : '').$change.'% t.o.v. vorige periode')
                ->color($change === null ? 'gray' : ($change >= 0 ? 'success' : 'danger')),
            Stat::make('Aanmelden afgerond', $pct($data['finished'], $data['signups']))->description($data['finished'].' van '.$data['signups'].' staan online'),
            Stat::make('Via pitchers', $data['via_pitcher'])->description($pct($data['via_pitcher'], $data['signups']).' van de aanmeldingen'),
            Stat::make("Demo's gestart", $data['demos'])->description($data['personal_demos'] === 1 ? '1 persoonlijke demo' : $data['personal_demos'].' persoonlijke demo\'s'),
            Stat::make('Demo → aanmelding', $pct($data['demo_signups'], $data['demos']))->description($data['demo_signups'].' zaken meldden zich aan na de demo'),
        ];
    }
}
