<?php

namespace App\Filament\Widgets\Insights;

use App\Services\AdminInsightsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Klanten, reviews, wachtlijst en klanten terughalen in de gekozen periode.
 */
class CustomerStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Klanten';

    protected function getStats(): array
    {
        $data = app(AdminInsightsService::class)->customers(AdminInsightsService::days($this->pageFilters['period'] ?? null));

        return [
            Stat::make('Nieuwe klanten', number_format($data['new_customers'], 0, ',', '.'))->description('Bij alle zaken samen'),
            Stat::make('Komt vaker terug', $data['returning_pct'] === null ? '-' : $data['returning_pct'].'%')->description('Klanten met 2+ bezoeken in deze periode'),
            Stat::make('Nieuwe klantaccounts', $data['new_accounts']),
            Stat::make('Reviews', $data['reviews'])->description($data['rating'] === null ? 'Nog geen reviews' : 'Gemiddeld '.number_format($data['rating'], 1, ',', '.').' ★'),
            Stat::make('Wachtlijst', $data['waitlist_booked'].' / '.$data['waitlist_total'])->description('Ingeschreven die alsnog een plek kregen'),
            Stat::make('Terughaalberichten', $data['winback_sent'])->description('Verstuurd door "klanten terughalen"'),
        ];
    }
}
