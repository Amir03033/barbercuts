<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\DisableShopScope;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
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
use Illuminate\Support\Str;
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
            ->brandLogoHeight('2.25rem')
            ->favicon('/favicon.svg?v=4')
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
                NavigationGroup::make('Platform'),
                NavigationGroup::make('Betalingen'),
                NavigationGroup::make('Support'),
                NavigationGroup::make('Wissel naar')->collapsible(false),
            ])
            // Terug naar de rest van BarberCuts: dezelfde keuzes als "Wissel van account" in de app.
            ->navigationItems(self::switchItems())
            ->userMenuItems(array_map(
                fn (NavigationItem $item): Action => Action::make('switch-'.Str::slug((string) $item->getLabel()))
                    ->label($item->getLabel())
                    ->icon($item->getIcon())
                    ->url($item->getUrl())
                    ->visible(fn (): bool => $item->isVisible()),
                self::switchItems(),
            ))
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

    /**
     * Links van het beheer naar de andere rollen van de ingelogde admin: zijn zaak, zijn afspraken, de demo en het
     * pitcherdashboard (alleen als hij die rol heeft), en de website.
     *
     * @return list<NavigationItem>
     */
    private static function switchItems(): array
    {
        $user = fn (): ?User => ($user = auth()->user()) instanceof User ? $user : null;

        return [
            NavigationItem::make('Mijn zaak')->url('/app')->icon(Heroicon::OutlinedScissors)->group('Wissel naar')->sort(1)
                ->visible(fn (): bool => $user()?->shops()->whereNull('onboarding_step')->where('is_demo', false)->exists() === true),
            NavigationItem::make('Pitcherdashboard')->url('/pitcher')->icon(Heroicon::OutlinedChartBar)->group('Wissel naar')->sort(2)
                ->visible(fn (): bool => $user()?->is_pitcher === true),
            NavigationItem::make('Demo')->url('/demo')->icon(Heroicon::OutlinedEye)->group('Wissel naar')->sort(3)
                ->visible(fn (): bool => $user()?->is_pitcher === true),
            NavigationItem::make('Mijn afspraken')->url('/account')->icon(Heroicon::OutlinedCalendarDays)->group('Wissel naar')->sort(4),
            NavigationItem::make('Naar de website')->url('/')->icon(Heroicon::OutlinedGlobeAlt)->group('Wissel naar')->sort(5),
        ];
    }
}
