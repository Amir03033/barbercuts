<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\Plan;
use App\Services\ShopAdminService;
use App\Support\Features;
use App\Support\PlatformSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Feature flags en gereserveerde slugs (docs/BOUWOPDRACHT.md §11). Wat hier staat gaat voor de waarden uit .env.
 */
class PlatformSettingsPage extends Page
{
    protected string $view = 'filament.pages.platform-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Instellingen';

    protected static ?string $title = 'Instellingen';

    protected static ?string $slug = 'instellingen';

    protected static ?int $navigationSort = 9;

    public bool $deposits = false;

    public bool $sms = false;

    public bool $whatsapp = false;

    public string $extraSlugs = '';

    public function mount(): void
    {
        $this->deposits = Features::deposits();
        $this->sms = Features::sms();
        $this->whatsapp = Features::whatsapp();
        $this->extraSlugs = implode("\n", (array) PlatformSettings::get('reserved_slugs_extra', []));
    }

    public function save(): void
    {
        $slugs = collect(preg_split('/[\s,]+/', strtolower($this->extraSlugs)) ?: [])
            ->map(fn (string $slug): string => trim($slug))
            ->filter()
            ->unique()
            ->values();

        $invalid = $slugs->first(fn (string $slug): bool => preg_match('/^[a-z0-9_]+(-[a-z0-9_]+)*$/', $slug) !== 1);

        if ($invalid !== null) {
            Notification::make()->title("\"{$invalid}\" is geen geldige slug")->danger()->send();

            return;
        }

        PlatformSettings::set('features.deposits', $this->deposits);
        PlatformSettings::set('features.sms', $this->sms);
        PlatformSettings::set('features.whatsapp', $this->whatsapp);
        PlatformSettings::set('reserved_slugs_extra', $slugs->all());

        ActivityLog::record('settings.updated', null, null, [
            'deposits' => $this->deposits, 'sms' => $this->sms, 'whatsapp' => $this->whatsapp, 'reserved_slugs_extra' => $slugs->all(),
        ]);

        Notification::make()->title('Instellingen opgeslagen')->success()->send();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $plan = Plan::current();
        $days = $plan !== null ? $plan->trial_days : (int) config('barbercuts.billing.trial_days');

        return [
            Action::make('enableBilling')->label('Betalen via Mollie aanzetten')->icon(Heroicon::OutlinedCreditCard)->color('success')
                ->visible(fn (): bool => ! Features::billing())
                ->requiresConfirmation()
                ->modalHeading('Betalen via Mollie aanzetten?')
                ->modalDescription("Zaken die nu gratis meedraaien krijgen een proefperiode van {$days} dagen en daarna moeten ze betalen. Zaken met een gratis periode van jou behouden die. Dit kun je later weer uitzetten.")
                ->action(function () use ($days): void {
                    $updated = app(ShopAdminService::class)->enableBilling($days);

                    Notification::make()->title('Facturering staat aan')->body("{$updated} zaken kregen een proefperiode van {$days} dagen.")->success()->send();
                }),
            Action::make('disableBilling')->label('Betalen via Mollie uitzetten')->icon(Heroicon::OutlinedCreditCard)->color('gray')
                ->visible(fn (): bool => Features::billing())
                ->requiresConfirmation()
                ->modalHeading('Betalen via Mollie uitzetten?')
                ->modalDescription('Alle zaken zijn dan weer gratis. Eigenaren zien bij Abonnement dat betalen tijdelijk uit staat. Bestaande abonnementen bij Mollie lopen door; zeg die zelf op in Mollie als dat nodig is.')
                ->action(function (): void {
                    app(ShopAdminService::class)->disableBilling();

                    Notification::make()->title('Facturering staat uit')->success()->send();
                }),
        ];
    }

    public function billingEnabled(): bool
    {
        return Features::billing();
    }
}
