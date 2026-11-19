<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\Plan;
use App\Services\ShopAdminService;
use App\Support\Features;
use App\Support\PlatformSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Feature flags en gereserveerde slugs (docs/BOUWOPDRACHT.md §11). Wat hier staat gaat voor de waarden uit .env.
 */
class PlatformSettingsPage extends Page
{
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

    /**
     * De pagina bestaat uit twee kaarten: de status van facturering en een formulier met de functies.
     */
    public function content(Schema $schema): Schema
    {
        $plan = Plan::current();

        return $schema->components([
            Section::make('Facturering')
                ->icon(Heroicon::OutlinedCreditCard)
                ->description(fn (): string => $this->billingEnabled()
                    ? 'Zaken betalen na hun proefperiode via Mollie.'
                    : 'Het platform is gratis (gratis start). Zaken zien nergens betaalschermen. Zet facturering aan met de knop rechtsboven.')
                ->schema([
                    Text::make(fn (): string => $this->billingEnabled() ? 'Staat aan' : 'Staat uit')
                        ->badge()
                        ->color(fn (): string => $this->billingEnabled() ? 'success' : 'gray'),
                    Text::make($plan !== null
                        ? 'Plan "'.$plan->name.'": '.money($plan->price_cents).' per maand, '.$plan->trial_days.' dagen proef. Aanpassen kan bij Plannen.'
                        : 'Er is nog geen actief plan. Maak er een aan bij Plannen.')
                        ->color('gray'),
                ]),
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Opslaan')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Functies')
                ->icon(Heroicon::OutlinedSparkles)
                ->description('Wat hier staat overschrijft de standaard uit .env.')
                ->schema([
                    Toggle::make('deposits')
                        ->label('Aanbetalingen')
                        ->helperText('Nog niet klaar voor gebruik. Laat dit uit tot een volgende update.'),
                    Toggle::make('sms')
                        ->label('SMS-berichten')
                        ->helperText('Herinneringen per sms. Zet dit alleen aan als de sms-provider is ingesteld.'),
                    Toggle::make('whatsapp')
                        ->label('WhatsApp-berichten')
                        ->helperText('Herinneringen per WhatsApp. Zet dit alleen aan als de templates zijn goedgekeurd.'),
                ]),
            Section::make('Gereserveerde slugs')
                ->icon(Heroicon::OutlinedLockClosed)
                ->description('Namen die geen zaak als boekingslink mag kiezen, naast de vaste lijst.')
                ->schema([
                    Textarea::make('extraSlugs')
                        ->label('Extra gereserveerde namen')
                        ->helperText('Eén per regel, of gescheiden door een komma.')
                        ->rows(5),
                ]),
        ]);
    }

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
