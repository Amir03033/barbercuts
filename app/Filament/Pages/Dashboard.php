<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Barbershops\BarbershopResource;
use App\Filament\Resources\Plans\PlanResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Startscherm van /admin: een begroeting, snelle knoppen en bovenaan wat aandacht nodig heeft.
 */
class Dashboard extends BaseDashboard
{
    public function getHeading(): string|Htmlable
    {
        $hour = (int) now('Europe/Amsterdam')->format('G');
        $greeting = match (true) {
            $hour < 6 => 'Goedenacht',
            $hour < 12 => 'Goedemorgen',
            $hour < 18 => 'Goedemiddag',
            default => 'Goedenavond',
        };

        return $greeting.', '.explode(' ', (string) auth()->user()?->name)[0];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return ucfirst(now('Europe/Amsterdam')->locale('nl')->isoFormat('dddd D MMMM YYYY')).' · zo staat het platform ervoor';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('shops')->label('Zaken')->icon(Heroicon::OutlinedBuildingStorefront)->color('gray')->url(BarbershopResource::getUrl('index')),
            Action::make('plans')->label('Prijs en proef')->icon(Heroicon::OutlinedCurrencyEuro)->color('gray')->url(PlanResource::getUrl('index')),
            Action::make('settings')->label('Instellingen')->icon(Heroicon::OutlinedCog6Tooth)->url(PlatformSettingsPage::getUrl()),
        ];
    }
}
