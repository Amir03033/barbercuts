<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\DisableShopScope;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Adminpanel voor de platformeigenaar op /admin (docs/BOUWOPDRACHT.md §11): alleen users met is_admin, met 2FA.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('BarberCuts admin')
            ->login()
            ->profile()
            ->darkMode(false)
            ->colors(['primary' => Color::hex('#0071e3'), 'gray' => Color::Zinc])
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2rem')
            ->favicon('/favicon.svg')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            // Lokale Inter en het eigen thema; geen verbinding met externe lettertype-diensten.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): HtmlString => new HtmlString(Blade::render('@fonts').Vite::useBuildDirectory('build')('resources/css/admin.css')))
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: true)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->navigationGroups([
                NavigationGroup::make('Platform')->icon(Heroicon::OutlinedGlobeAlt),
                NavigationGroup::make('Betalingen')->icon(Heroicon::OutlinedBanknotes),
                NavigationGroup::make('Support')->icon(Heroicon::OutlinedLifebuoy),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                DisableShopScope::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
