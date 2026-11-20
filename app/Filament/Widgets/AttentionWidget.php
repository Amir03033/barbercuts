<?php

namespace App\Filament\Widgets;

use App\Enums\AccessState;
use App\Filament\Resources\Barbershops\BarbershopResource;
use App\Models\Barbershop;
use App\Support\Features;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Bovenaan het dashboard: wat vandaag aandacht nodig heeft, zodat je niets hoeft te zoeken.
 */
class AttentionWidget extends StatsOverviewWidget
{
    /** Niet elke 5 seconden opnieuw laden (standaard in Filament): dat maakte het dashboard traag bij scrollen. */
    protected ?string $pollingInterval = null;

    protected static ?int $sort = -10;

    protected ?string $heading = 'Aandacht nodig';

    protected function getStats(): array
    {
        $billing = Features::billing();
        $url = BarbershopResource::getUrl('index');

        $endingSoon = $billing
            ? Barbershop::query()->inAccessState(AccessState::Trial)->where('trial_ends_at', '<=', now()->addDays(3))->count()
            : 0;
        $failed = $billing ? Barbershop::query()->inAccessState(AccessState::PastDue)->count() : 0;
        $expired = $billing ? Barbershop::query()->inAccessState(AccessState::Expired)->count() : 0;
        $new = Barbershop::query()->listed()->where('created_at', '>=', now()->subDays(7))->count();

        $off = 'Facturering staat uit';

        return [
            Stat::make('Proef loopt binnen 3 dagen af', $endingSoon)
                ->description($billing ? ($endingSoon > 0 ? 'Zij moeten bijna een abonnement kiezen' : 'Niemand bijna aan het einde') : $off)
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($endingSoon > 0 ? 'warning' : 'gray')->url($url),
            Stat::make('Betaling mislukt', $failed)
                ->description($billing ? ($failed > 0 ? 'Nog 7 dagen respijt, dan alleen-lezen' : 'Alles wordt betaald') : $off)
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($failed > 0 ? 'danger' : 'gray')->url($url),
            Stat::make('Verlopen (alleen-lezen)', $expired)
                ->description($billing ? ($expired > 0 ? 'Klanten kunnen niet boeken' : 'Geen verlopen zaken') : $off)
                ->descriptionIcon(Heroicon::OutlinedLockClosed)
                ->color($expired > 0 ? 'danger' : 'gray')->url($url),
            Stat::make('Nieuwe zaken (7 dagen)', $new)
                ->description('Aangemeld en live')
                ->descriptionIcon(Heroicon::OutlinedSparkles)
                ->color('success')->url($url),
        ];
    }
}
