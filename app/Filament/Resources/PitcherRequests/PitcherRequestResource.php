<?php

namespace App\Filament\Resources\PitcherRequests;

use App\Enums\PitcherRequestStatus;
use App\Enums\PitcherRequestType;
use App\Filament\Resources\PitcherRequests\Pages\ListPitcherRequests;
use App\Models\ActivityLog;
use App\Models\PitcherRequest;
use App\Notifications\PitcherRequestHandled;
use App\Services\ShopAdminService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Verzoeken van pitchers: een proef verlengen, een wens van een kapper of een vraag. Afhandelen stuurt de pitcher een mail.
 */
class PitcherRequestResource extends Resource
{
    protected static ?string $model = PitcherRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Pitcherverzoeken';

    protected static ?string $modelLabel = 'verzoek';

    protected static ?string $pluralModelLabel = 'verzoeken van pitchers';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = PitcherRequest::query()->where('status', PitcherRequestStatus::Open->value)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['pitcher', 'barbershop']))
            ->columns([
                TextColumn::make('created_at')->label('Datum')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('pitcher.name')->label('Pitcher')->searchable(),
                TextColumn::make('type')->label('Soort')->badge()->formatStateUsing(fn (PitcherRequestType $state): string => $state->label()),
                TextColumn::make('barbershop.name')->label('Zaak')->placeholder('-')->searchable(),
                TextColumn::make('message')->label('Bericht')->wrap()->limit(200),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (PitcherRequestStatus $state): string => $state->label())
                    ->color(fn (PitcherRequestStatus $state): string => $state->color()),
                TextColumn::make('answer')->label('Antwoord')->wrap()->limit(120)->placeholder('-')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Status')->options(collect(PitcherRequestStatus::cases())->mapWithKeys(fn (PitcherRequestStatus $s): array => [$s->value => $s->label()])->all())->default(PitcherRequestStatus::Open->value),
                SelectFilter::make('type')->label('Soort')->options(collect(PitcherRequestType::cases())->mapWithKeys(fn (PitcherRequestType $t): array => [$t->value => $t->label()])->all()),
            ])
            ->recordActions([
                self::extendTrialAction(),
                self::handleAction(),
                self::declineAction(),
            ]);
    }

    /**
     * Verleng de proef van de zaak en meld het de pitcher.
     */
    public static function extendTrialAction(): Action
    {
        return Action::make('extendTrial')->label('Proef verlengen')->icon(Heroicon::OutlinedClock)->color('success')
            ->visible(fn (PitcherRequest $record): bool => $record->isOpen() && $record->type === PitcherRequestType::TrialExtension && $record->barbershop !== null)
            ->modalHeading('Proef verlengen')
            ->schema([
                Select::make('days')->label('Met hoeveel dagen?')->required()->default('7')
                    ->options(['3' => '3 dagen', '7' => '7 dagen', '14' => '14 dagen', '30' => '30 dagen']),
                Textarea::make('answer')->label('Bericht aan de pitcher')->rows(2)->maxLength(500),
            ])
            ->action(function (array $data, PitcherRequest $record, ShopAdminService $shops): void {
                $shops->extendTrial($record->barbershop, (int) $data['days']);

                self::finish($record, PitcherRequestStatus::Done, $data['answer'] ?? null, ['extended_days' => (int) $data['days']]);

                Notification::make()->title('Proef verlengd en pitcher op de hoogte gebracht')->success()->send();
            });
    }

    public static function handleAction(): Action
    {
        return Action::make('handle')->label('Afhandelen')->icon(Heroicon::OutlinedCheck)
            ->visible(fn (PitcherRequest $record): bool => $record->isOpen())
            ->modalHeading('Als afgehandeld markeren')
            ->schema([Textarea::make('answer')->label('Antwoord aan de pitcher (optioneel)')->rows(3)->maxLength(500)])
            ->action(function (array $data, PitcherRequest $record): void {
                self::finish($record, PitcherRequestStatus::Done, $data['answer'] ?? null);

                Notification::make()->title('Afgehandeld')->success()->send();
            });
    }

    public static function declineAction(): Action
    {
        return Action::make('decline')->label('Afwijzen')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
            ->visible(fn (PitcherRequest $record): bool => $record->isOpen())
            ->modalHeading('Verzoek afwijzen')
            ->schema([Textarea::make('answer')->label('Waarom niet?')->required()->rows(3)->maxLength(500)])
            ->action(function (array $data, PitcherRequest $record): void {
                self::finish($record, PitcherRequestStatus::Declined, $data['answer']);

                Notification::make()->title('Afgewezen')->success()->send();
            });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function finish(PitcherRequest $record, PitcherRequestStatus $status, ?string $answer, array $context = []): void
    {
        $record->forceFill([
            'status' => $status,
            'answer' => filled($answer) ? trim((string) $answer) : null,
            'handled_at' => now(),
        ])->save();

        ActivityLog::record('pitcher_request.'.$status->value, $record->barbershop, $record, ['type' => $record->type->value] + $context);

        $record->pitcher->notify(new PitcherRequestHandled($record->id));
    }

    public static function getPages(): array
    {
        return ['index' => ListPitcherRequests::route('/')];
    }
}
