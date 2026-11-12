<?php

namespace App\Filament\Resources\Barbershops;

use App\Enums\AccessState;
use App\Enums\AppointmentStatus;
use App\Enums\ShopRole;
use App\Filament\Resources\Barbershops\Pages\ListBarbershops;
use App\Filament\Resources\Barbershops\Pages\ViewBarbershop;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Services\ShopAdminService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use UnitEnum;

/**
 * Zaken beheren (docs/BOUWOPDRACHT.md §11): lijst met filters, detail en acties (gratis periode, trial verlengen,
 * blokkeren, inloggen als eigenaar).
 */
class BarbershopResource extends Resource
{
    protected static ?string $model = Barbershop::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Zaken';

    protected static ?string $modelLabel = 'zaak';

    protected static ?string $pluralModelLabel = 'zaken';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * De admin ziet elke zaak. De BarbershopPolicy is voor leden van een zaak in /app en zou de admin hier weigeren.
     */
    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return Response::allow();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Zaak')->columns(2)->schema([
                TextEntry::make('name')->label('Naam'),
                TextEntry::make('slug')->label('Link')->state(fn (Barbershop $record): string => $record->bookingUrlLabel()),
                TextEntry::make('city')->label('Plaats')->placeholder('-'),
                TextEntry::make('phone')->label('Telefoon')->placeholder('-'),
                TextEntry::make('email')->label('E-mail')->placeholder('-'),
                TextEntry::make('created_at')->label('Aangemeld')->dateTime('d-m-Y H:i'),
                TextEntry::make('onboarding')->label('Aanmelden')->state(fn (Barbershop $record): string => $record->hasFinishedOnboarding() ? 'Afgerond' : 'Stap '.$record->onboarding_step.' van 6'),
                TextEntry::make('is_public')->label('Boekingspagina')->state(fn (Barbershop $record): string => $record->is_public ? 'Online' : 'Niet online'),
            ]),
            Section::make('Abonnement')->columns(2)->schema([
                TextEntry::make('state')->label('Status')->state(fn (Barbershop $record): string => $record->accessState()->label())
                    ->badge()->color(fn (Barbershop $record): string => $record->accessState()->color()),
                TextEntry::make('plan.name')->label('Plan')->placeholder('-'),
                TextEntry::make('trial_ends_at')->label('Proefperiode tot')->dateTime('d-m-Y')->placeholder('-'),
                TextEntry::make('free_until')->label('Gratis tot')->dateTime('d-m-Y')->placeholder('-'),
                TextEntry::make('free_reason')->label('Reden gratis')->placeholder('-'),
                TextEntry::make('suspended_reason')->label('Reden blokkade')->placeholder('-')->visible(fn (Barbershop $record): bool => $record->suspended_at !== null),
            ]),
            Section::make('Gebruik')->columns(3)->schema([
                TextEntry::make('staff')->label('Kappers')->state(fn (Barbershop $record): string => $record->staffMembers()->pluck('name')->implode(', ') ?: '-')->columnSpanFull(),
                TextEntry::make('customers_count')->label('Klanten')->state(fn (Barbershop $record): int => $record->customers()->count()),
                TextEntry::make('appointments_count')->label('Afspraken')->state(fn (Barbershop $record): int => $record->appointments()->count()),
                TextEntry::make('revenue')->label('Omzet verwerkt')->state(fn (Barbershop $record): string => money((int) Appointment::query()
                    ->where('barbershop_id', $record->id)->where('status', AppointmentStatus::Completed->value)->sum('amount_paid_cents'))),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Demozaken van pitchers (fase 6) horen niet tussen de echte zaken.
            ->modifyQueryUsing(fn (Builder $query) => $query->where('is_demo', false)->with('plan'))
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('city')->label('Plaats')->searchable()->sortable()->toggleable(),
                TextColumn::make('state')->label('Status')
                    ->state(fn (Barbershop $record): string => $record->accessState()->label())
                    ->badge()->color(fn (Barbershop $record): string => $record->accessState()->color()),
                TextColumn::make('onboarding')->label('Aanmelden')
                    ->state(fn (Barbershop $record): string => $record->hasFinishedOnboarding() ? 'Afgerond' : 'Stap '.$record->onboarding_step)->toggleable(),
                TextColumn::make('trial_ends_at')->label('Proef tot')->date('d-m-Y')->sortable()->placeholder('-')->toggleable(),
                TextColumn::make('free_until')->label('Gratis tot')->date('d-m-Y')->sortable()->placeholder('-')->toggleable(),
                TextColumn::make('appointments_count')->label('Afspraken')->counts('appointments')->sortable()->toggleable(),
                TextColumn::make('created_at')->label('Aangemeld')->date('d-m-Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('state')->label('Status')
                    ->options(collect(AccessState::cases())->mapWithKeys(fn (AccessState $state): array => [$state->value => $state->label()])->all())
                    ->query(function (Builder $query, array $data): Builder {
                        /** @var Builder<Barbershop> $query */
                        return filled($data['value'] ?? null) ? $query->inAccessState(AccessState::from($data['value'])) : $query;
                    }),
                SelectFilter::make('city')->label('Plaats')
                    ->options(fn (): array => Barbershop::query()->real()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city', 'city')->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('Bekijk'),
                ActionGroup::make([
                    self::freePeriodAction(),
                    self::extendTrialAction(),
                    self::suspendAction(),
                    self::unsuspendAction(),
                    self::impersonateAction(),
                    Action::make('open')->label('Boekingspagina openen')->icon(Heroicon::OutlinedGlobeAlt)
                        ->url(fn (Barbershop $record): string => $record->bookingUrl(), shouldOpenInNewTab: true),
                ]),
            ]);
    }

    public static function freePeriodAction(): Action
    {
        return Action::make('freePeriod')->label('Geef gratis periode')->icon(Heroicon::OutlinedGift)
            ->modalHeading('Gratis periode geven')
            ->schema([
                Select::make('duration')->label('Duur')->required()->live()->default('3')
                    ->options(['3' => '3 maanden', '6' => '6 maanden', '12' => '12 maanden', 'custom' => 'Tot een datum']),
                DatePicker::make('until')->label('Tot en met')->minDate(now()->addDay())
                    ->visible(fn (Get $get): bool => $get('duration') === 'custom')
                    ->required(fn (Get $get): bool => $get('duration') === 'custom'),
                Textarea::make('reason')->label('Reden')->rows(2),
            ])
            ->action(function (array $data, Barbershop $record): void {
                $until = $data['duration'] === 'custom'
                    ? CarbonImmutable::parse($data['until'])->endOfDay()
                    : now()->addMonths((int) $data['duration'])->endOfDay();

                app(ShopAdminService::class)->giveFreePeriod($record, $until, (string) ($data['reason'] ?? ''));

                Notification::make()->title('Gratis periode tot '.$until->format('d-m-Y').' ingesteld')->success()->send();
            });
    }

    public static function extendTrialAction(): Action
    {
        return Action::make('extendTrial')->label('Verleng proefperiode')->icon(Heroicon::OutlinedClock)
            ->modalHeading('Proefperiode verlengen')
            ->schema([
                TextInput::make('days')->label('Aantal dagen')->numeric()->integer()->minValue(1)->maxValue(365)->default(14)->required(),
            ])
            ->action(function (array $data, Barbershop $record): void {
                app(ShopAdminService::class)->extendTrial($record, (int) $data['days']);

                Notification::make()->title('Proefperiode verlengd')->success()->send();
            });
    }

    public static function suspendAction(): Action
    {
        return Action::make('suspend')->label('Blokkeren')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
            ->visible(fn (Barbershop $record): bool => $record->suspended_at === null)
            ->modalHeading('Zaak blokkeren')
            ->modalDescription('De zaak kan niet meer inloggen en de boekingspagina is niet beschikbaar. Er wordt niets verwijderd.')
            ->schema([Textarea::make('reason')->label('Reden')->rows(2)->required()])
            ->action(function (array $data, Barbershop $record): void {
                app(ShopAdminService::class)->suspend($record, (string) $data['reason']);

                Notification::make()->title('Zaak geblokkeerd')->success()->send();
            });
    }

    public static function unsuspendAction(): Action
    {
        return Action::make('unsuspend')->label('Deblokkeren')->icon(Heroicon::OutlinedLockOpen)
            ->visible(fn (Barbershop $record): bool => $record->suspended_at !== null)
            ->requiresConfirmation()
            ->action(function (Barbershop $record): void {
                app(ShopAdminService::class)->unsuspend($record);

                Notification::make()->title('Zaak gedeblokkeerd')->success()->send();
            });
    }

    /**
     * Inloggen als de eigenaar, via een tijdelijk gesigneerde link die pas bij het klikken wordt gemaakt (geen CSRF via een gewone link).
     */
    public static function impersonateAction(): Action
    {
        return Action::make('impersonate')->label('Inloggen als eigenaar')->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->visible(fn (Barbershop $record): bool => $record->users()->wherePivot('role', ShopRole::Owner->value)->exists())
            ->requiresConfirmation()
            ->modalDescription('Je wordt ingelogd als de eigenaar van deze zaak. Bovenaan staat een banner met een knop om terug te gaan. Dit wordt gelogd.')
            ->action(fn (Barbershop $record, Component $livewire) => $livewire->redirect(
                URL::temporarySignedRoute('impersonate.start', now()->addMinute(), ['shop' => $record->id])
            ));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBarbershops::route('/'),
            'view' => ViewBarbershop::route('/{record}'),
        ];
    }
}
