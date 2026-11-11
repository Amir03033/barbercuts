<?php

namespace App\Filament\Widgets;

use App\Services\PlatformStatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ShopsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Zaken';

    protected function getStats(): array
    {
        $stats = app(PlatformStatsService::class);
        $stats->ensureToday();

        $total = $stats->latest('shops_total');
        $onboarded = $stats->latest('shops_onboarded');
        $perWeek = collect($stats->series('signups', 7))->sum();

        return [
            Stat::make('Zaken', $total)->description($perWeek.' nieuw in 7 dagen')->color('primary'),
            Stat::make('Aanmelden afgerond', $total > 0 ? round($onboarded / $total * 100).'%' : '-')->description($onboarded.' van '.$total),
            Stat::make('Proefperiode', $stats->latest('shops_trial'))->color('info'),
            Stat::make('Gratis', $stats->latest('shops_free'))->color('gray'),
            Stat::make('Betaald', $stats->latest('shops_active'))->color('success'),
            Stat::make('Betaling mislukt', $stats->latest('shops_past_due'))->color('warning'),
            Stat::make('Verlopen', $stats->latest('shops_expired'))->color('danger'),
            Stat::make('Geblokkeerd', $stats->latest('shops_suspended'))->color('danger'),
        ];
    }
}
