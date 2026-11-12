<?php

namespace App\Filament\Resources\Pitchers;

use App\Filament\Resources\Pitchers\Pages\ListPitchers;
use App\Models\ActivityLog;
use App\Models\Barbershop;
use App\Models\User;
use App\Notifications\PitcherInvitation;
use App\Services\Demo\DemoShopService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Pitchers (fase 6): accounts die de demo mogen starten. Toevoegen, uitnodiging opnieuw sturen en afsluiten.
 */
class PitcherResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Pitchers';

    protected static ?string $modelLabel = 'pitcher';

    protected static ?string $pluralModelLabel = 'pitchers';

    protected static ?string $slug = 'pitchers';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_pitcher', true);
    }

    /**
     * Maak iemand pitcher: een bestaand account krijgt toegang, anders komt er een nieuw account. Daarna gaat de uitnodiging uit.
     */
    public static function invite(string $name, string $email): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = new User(['name' => $name, 'email' => $email, 'password' => Str::random(40)]);
            // De uitnodiging gaat naar dit adres; wie via de link een wachtwoord kiest, heeft het adres daarmee bevestigd.
            $user->email_verified_at = now();
        }

        $user->is_pitcher = true;
        $user->save();

        $user->notify(new PitcherInvitation);
        ActivityLog::record('pitcher.invited', null, $user);

        return $user;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable()->copyable(),
                TextColumn::make('demo_count')->label('Demo\'s gestart')->sortable(),
                TextColumn::make('last_demo_at')->label('Laatste demo')->since()->sortable()->placeholder('Nog niet'),
                IconColumn::make('running')->label('Demo loopt')->boolean()
                    ->state(fn (User $record): bool => app(DemoShopService::class)->current($record) !== null),
            ])
            ->defaultSort('last_demo_at', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('resend')->label('Uitnodiging opnieuw sturen')->icon(Heroicon::OutlinedEnvelope)
                        ->requiresConfirmation()
                        ->action(function (User $record): void {
                            $record->notify(new PitcherInvitation);

                            Notification::make()->title('Uitnodiging verstuurd')->success()->send();
                        }),
                    Action::make('revoke')->label('Toegang intrekken')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('De pitcher kan de demo niet meer openen en een lopende demozaak wordt gewist. Het account zelf blijft bestaan.')
                        ->action(function (User $record, DemoShopService $demos): void {
                            Barbershop::query()->where('is_demo', true)->where('demo_pitcher_id', $record->id)->get()
                                ->each(fn (Barbershop $shop) => $demos->delete($shop));

                            $record->forceFill(['is_pitcher' => false])->save();
                            ActivityLog::record('pitcher.revoked', null, $record);

                            Notification::make()->title('Toegang ingetrokken')->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPitchers::route('/')];
    }
}
