<?php

namespace App\Filament\Widgets;

use App\Services\PlatformStatsService;
use App\Support\Features;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MoneyOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Geld';

    protected function getStats(): array
    {
        $stats = app(PlatformStatsService::class);
        $stats->ensureToday();

        $percent = fn (int $bp): string => number_format($bp / 100, 1, ',', '.').'%';

        return [
            Stat::make('MRR', money($stats->latest('mrr_cents')))->description(Features::billing() ? 'Terugkerende omzet per maand' : 'Facturering staat uit')->color('success'),
            Stat::make('Inkomsten deze maand', money($stats->latest('revenue_month_cents')))->description('Betaalde abonnementen'),
            Stat::make('Churn deze maand', $percent($stats->latest('churn_bp')))->description('Opzeggingen t.o.v. begin van de maand'),
            Stat::make('Conversie proef → betaald', $percent($stats->latest('conversion_bp')))->description('Zaken die na de proefperiode betalen'),
        ];
    }
}
