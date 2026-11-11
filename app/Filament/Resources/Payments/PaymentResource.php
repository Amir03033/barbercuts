<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Payment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Betalingen van abonnementen (en later aanbetalingen), alleen lezen (§11).
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Betalingen';

    protected static ?string $navigationLabel = 'Betalingen';

    protected static ?string $modelLabel = 'betaling';

    protected static ?string $pluralModelLabel = 'betalingen';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Datum')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('barbershop.name')->label('Zaak')->searchable(),
                TextColumn::make('amount_cents')->label('Bedrag')->formatStateUsing(fn (int $state): string => money($state))->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'failed', 'canceled', 'expired' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('paid_at')->label('Betaald op')->dateTime('d-m-Y H:i')->placeholder('-')->toggleable(),
                TextColumn::make('mollie_payment_id')->label('Mollie')->copyable()->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Status')->options(['paid' => 'Betaald', 'open' => 'Open', 'failed' => 'Mislukt', 'canceled' => 'Geannuleerd', 'expired' => 'Verlopen']),
            ])
            ->headerActions([
                Action::make('mollie')->label('Mollie-dashboard')->icon(Heroicon::OutlinedGlobeAlt)->url('https://my.mollie.com/dashboard/', shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }
}
