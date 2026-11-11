<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Activiteitenlogboek: wie deed wat (§11), alleen lezen.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Logboek';

    protected static ?string $modelLabel = 'logregel';

    protected static ?string $pluralModelLabel = 'logboek';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'barbershop']))
            ->columns([
                TextColumn::make('created_at')->label('Wanneer')->dateTime('d-m-Y H:i:s')->sortable(),
                TextColumn::make('user.name')->label('Wie')->placeholder('-'),
                TextColumn::make('action')->label('Actie')->badge()->searchable(),
                TextColumn::make('barbershop.name')->label('Zaak')->placeholder('-')->searchable(),
                TextColumn::make('properties')->label('Details')->limit(60)->placeholder('-')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE) : (string) $state),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')->label('Actie')->options(fn (): array => ActivityLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListActivityLogs::route('/')];
    }
}
