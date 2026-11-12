<?php

namespace App\Filament\Resources\Reviews;

use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Review;
use App\Services\ReviewService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Reviews over alle zaken (fase 7): gemelde reviews beoordelen, verbergen of weer tonen.
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Reviews';

    protected static ?string $modelLabel = 'review';

    protected static ?string $pluralModelLabel = 'reviews';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = Review::query()->whereNotNull('reported_at')->whereNull('hidden_at')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['barbershop', 'customer', 'staffMember']))
            ->columns([
                TextColumn::make('barbershop.name')->label('Zaak')->searchable()->sortable(),
                TextColumn::make('rating')->label('Sterren')->sortable(),
                TextColumn::make('comment')->label('Tekst')->wrap()->limit(160)->placeholder('-')->searchable(),
                TextColumn::make('customer.name')->label('Klant')->toggleable(),
                TextColumn::make('report_reason')->label('Melding van de zaak')->wrap()->placeholder('-'),
                IconColumn::make('hidden')->label('Verborgen')->boolean()->state(fn (Review $record): bool => $record->hidden_at !== null),
                TextColumn::make('created_at')->label('Geplaatst')->date('d-m-Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('reported')->label('Gemeld')->nullable()->attribute('reported_at'),
                TernaryFilter::make('hidden')->label('Verborgen')->nullable()->attribute('hidden_at'),
            ])
            ->recordActions([
                Action::make('hide')->label('Verbergen')->icon(Heroicon::OutlinedEyeSlash)->color('danger')
                    ->visible(fn (Review $record): bool => $record->hidden_at === null)
                    ->requiresConfirmation()
                    ->modalDescription('De review verdwijnt van de pagina van de zaak en telt niet meer mee in de score.')
                    ->action(function (Review $record, ReviewService $reviews): void {
                        $reviews->setHidden($record, true);

                        Notification::make()->title('Review verborgen')->success()->send();
                    }),
                Action::make('show')->label('Weer tonen')->icon(Heroicon::OutlinedEye)
                    ->visible(fn (Review $record): bool => $record->hidden_at !== null)
                    ->requiresConfirmation()
                    ->action(function (Review $record, ReviewService $reviews): void {
                        $reviews->setHidden($record, false);

                        Notification::make()->title('Review staat weer online')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListReviews::route('/')];
    }
}
