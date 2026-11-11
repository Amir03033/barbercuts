<?php

namespace App\Filament\Resources\MessageLogs;

use App\Filament\Resources\MessageLogs\Pages\ListMessageLogs;
use App\Models\MessageLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Verstuurde berichten per kanaal, alleen lezen (§11). Wordt gevuld vanaf fase 5.
 */
class MessageLogResource extends Resource
{
    protected static ?string $model = MessageLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Berichten';

    protected static ?string $modelLabel = 'bericht';

    protected static ?string $pluralModelLabel = 'berichten';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Verstuurd')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('barbershop.name')->label('Zaak')->placeholder('-'),
                TextColumn::make('channel')->label('Kanaal')->badge(),
                TextColumn::make('type')->label('Soort'),
                TextColumn::make('to')->label('Aan')->searchable(),
                TextColumn::make('cost_cents')->label('Kosten')->formatStateUsing(fn (?int $state): string => $state === null ? '-' : money($state)),
                TextColumn::make('status')->label('Status')->badge()->color(fn (string $state): string => $state === 'failed' ? 'danger' : 'success'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('channel')->label('Kanaal')->options(['mail' => 'E-mail', 'push' => 'Push', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp']),
                SelectFilter::make('status')->label('Status')->options(['sent' => 'Verstuurd', 'failed' => 'Mislukt']),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMessageLogs::route('/')];
    }
}
