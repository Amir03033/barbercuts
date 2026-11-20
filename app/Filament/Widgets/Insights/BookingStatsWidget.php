<?php

namespace App\Filament\Widgets\Insights;

use App\Services\AdminInsightsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Afspraken bij alle echte zaken in de gekozen periode.
 */
class BookingStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Afspraken';

    protected function getStats(): array
    {
        $data = app(AdminInsightsService::class)->bookings(AdminInsightsService::days($this->pageFilters['period'] ?? null));
        $pct = fn (int $part): string => $data['total'] > 0 ? number_format($part / $data['total'] * 100, 1, ',', '.').'%' : '-';

        return [
            Stat::make('Afspraken', number_format($data['total'], 0, ',', '.'))->description($data['completed'].' voltooid'),
            Stat::make('Online geboekt', $pct($data['online']))->description($data['online'].' door klanten zelf'),
            Stat::make('Omzet verwerkt', money($data['revenue_cents']))->description(money($data['tips_cents']).' fooi'),
            Stat::make('Gemiddelde bon', money($data['avg_ticket_cents']))->description('Per voltooide afspraak'),
            Stat::make('Geannuleerd', $pct($data['cancelled']))->description($data['cancelled'].' afspraken'),
            Stat::make('No-show', $pct($data['no_show']))->description($data['no_show'].' afspraken')->color($data['total'] > 0 && $data['no_show'] / $data['total'] > 0.08 ? 'danger' : 'gray'),
        ];
    }
}
