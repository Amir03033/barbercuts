<?php

namespace App\Filament\Widgets;

use App\Services\PlatformStatsService;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UsageOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Gebruik';

    protected function getStats(): array
    {
        $stats = app(PlatformStatsService::class);
        $stats->ensureToday();

        $now = CarbonImmutable::now(PlatformStatsService::TIMEZONE);
        $month = fn (string $key): int => $stats->sum($key, $now->startOfMonth(), $now);
        $online = $month('appointments_online');
        $staff = $month('appointments_staff');
        $accounts = $stats->latest('customer_accounts');
        $guests = $stats->latest('guest_customers');

        return [
            Stat::make('Afspraken deze maand', $online + $staff)->description($online.' online · '.$staff.' door kapper'),
            Stat::make('Online aandeel', $online + $staff > 0 ? round($online / ($online + $staff) * 100).'%' : '-')->description('Door klanten zelf gemaakt'),
            Stat::make('Omzet verwerkt deze maand', money($month('revenue_cents')))->description('Door alle zaken samen'),
            Stat::make('No-show', number_format($stats->latest('no_show_bp') / 100, 1, ',', '.').'%')->description('Laatste 30 dagen'),
            Stat::make('Actieve kappers', $stats->latest('active_barbers')),
            Stat::make('Klanten met account', $accounts)->description($guests.' gasten'),
        ];
    }
}
