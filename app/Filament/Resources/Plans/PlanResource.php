<?php

namespace App\Filament\Resources\Plans;

use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Services\Billing\SubscriptionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Plannen (docs/BOUWOPDRACHT.md §11, B10): hier pas je de prijs aan. Een prijswijziging vraagt altijd of bestaande
 * abonnementen ook meegaan, daarom gaat de prijs alleen via de actie "Prijs wijzigen".
 */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyEuro;

    protected static string|UnitEnum|null $navigationGroup = 'Betalingen';

    protected static ?string $navigationLabel = 'Plannen';

    protected static ?string $modelLabel = 'plan';

    protected static ?string $pluralModelLabel = 'plannen';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Naam')->required()->maxLength(80),
            TextInput::make('price_cents')->label('Prijs per maand')->prefix('€')->numeric()->minValue(0.01)->step(0.01)->required()
                ->formatStateUsing(fn (mixed $state): ?float => $state === null ? null : $state / 100)
                ->dehydrateStateUsing(fn (mixed $state): int => (int) round(((float) $state) * 100))
                ->disabled(fn (?Plan $record): bool => $record !== null)
                ->dehydrated(fn (?Plan $record): bool => $record === null)
                ->helperText('Een bestaand plan wijzig je met de actie "Prijs wijzigen" in de lijst.'),
            TextInput::make('trial_days')->label('Dagen proefperiode')->numeric()->integer()->minValue(0)->maxValue(365)->default(14)->required(),
            Toggle::make('is_active')->label('Actief')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Naam')->sortable(),
                TextColumn::make('price_cents')->label('Prijs')->formatStateUsing(fn (int $state): string => money($state).' per maand')->sortable(),
                TextColumn::make('trial_days')->label('Proefperiode')->suffix(' dagen'),
                TextColumn::make('subscriptions_count')->label('Abonnementen')->counts('subscriptions'),
                IconColumn::make('is_active')->label('Actief')->boolean(),
            ])
            ->recordActions([
                self::changePriceAction(),
                EditAction::make()->label('Bewerken'),
            ]);
    }

    public static function changePriceAction(): Action
    {
        return Action::make('changePrice')->label('Prijs wijzigen')->icon(Heroicon::OutlinedCurrencyEuro)
            ->modalHeading('Prijs wijzigen')
            ->schema([
                TextInput::make('price')->label('Nieuwe prijs per maand')->prefix('€')->numeric()->minValue(0.01)->step(0.01)->required()
                    ->default(fn (Plan $record): float => $record->price_cents / 100),
                Radio::make('scope')->label('Voor wie geldt dit?')->required()->default('new')
                    ->options([
                        'new' => 'Alleen nieuwe abonnementen. Bestaande abonnementen houden hun prijs.',
                        'all' => 'Ook bestaande abonnementen. De eigenaren krijgen een e-mail.',
                    ]),
            ])
            ->action(function (array $data, Plan $record): void {
                $old = $record->price_cents;
                $new = (int) round(((float) $data['price']) * 100);
                $changed = app(SubscriptionService::class)->changePrice($record, $new, $data['scope'] === 'all');

                ActivityLog::record('plan.price_changed', null, $record, ['old_cents' => $old, 'new_cents' => $new, 'existing_updated' => $changed]);

                Notification::make()->title('Prijs gewijzigd')
                    ->body($data['scope'] === 'all' ? "{$changed} bestaande abonnementen bijgewerkt." : 'Alleen voor nieuwe abonnementen.')
                    ->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
