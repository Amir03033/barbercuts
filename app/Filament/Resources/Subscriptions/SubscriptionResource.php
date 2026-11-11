<?php

namespace App\Filament\Resources\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Models\Subscription;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Abonnementen, alleen lezen (§11). Wijzigen gaat via Mollie of via de acties op een zaak.
 */
class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Betalingen';

    protected static ?string $navigationLabel = 'Abonnementen';

    protected static ?string $modelLabel = 'abonnement';

    protected static ?string $pluralModelLabel = 'abonnementen';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('barbershop.name')->label('Zaak')->searchable()->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => match ($state) {
                        SubscriptionStatus::Pending => 'Wacht op betaling',
                        SubscriptionStatus::Active => 'Actief',
                        SubscriptionStatus::PastDue => 'Betaling mislukt',
                        SubscriptionStatus::Cancelled => 'Opgezegd',
                    })
                    ->color(fn (SubscriptionStatus $state): string => match ($state) {
                        SubscriptionStatus::Active => 'success',
                        SubscriptionStatus::PastDue => 'warning',
                        SubscriptionStatus::Cancelled => 'gray',
                        SubscriptionStatus::Pending => 'info',
                    }),
                TextColumn::make('price_cents')->label('Prijs')->formatStateUsing(fn (int $state): string => money($state))->sortable(),
                TextColumn::make('current_period_ends_at')->label('Betaald tot')->date('d-m-Y')->sortable()->placeholder('-'),
                TextColumn::make('mollie_subscription_id')->label('Mollie')->copyable()->placeholder('-')->toggleable(),
                TextColumn::make('created_at')->label('Gestart')->date('d-m-Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Status')->options(collect(SubscriptionStatus::cases())->mapWithKeys(fn (SubscriptionStatus $s): array => [$s->value => ucfirst($s->value)])->all()),
            ])
            ->headerActions([
                Action::make('mollie')->label('Mollie-dashboard')->icon(Heroicon::OutlinedGlobeAlt)->url('https://my.mollie.com/dashboard/', shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSubscriptions::route('/')];
    }
}
