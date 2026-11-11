<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\ActivityLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use UnitEnum;

/**
 * Gebruikers (docs/BOUWOPDRACHT.md §11): zoeken, admin-rechten, e-mail bevestigen, wachtwoord-reset sturen.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Gebruikers';

    protected static ?string $modelLabel = 'gebruiker';

    protected static ?string $pluralModelLabel = 'gebruikers';

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable()->sortable()->copyable(),
                TextColumn::make('phone')->label('Telefoon')->searchable()->placeholder('-')->toggleable(),
                IconColumn::make('is_admin')->label('Admin')->boolean(),
                IconColumn::make('email_verified_at')->label('Bevestigd')->boolean()->state(fn (User $record): bool => $record->hasVerifiedEmail()),
                TextColumn::make('created_at')->label('Aangemeld')->date('d-m-Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_admin')->label('Admin'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('toggleAdmin')
                        ->label(fn (User $record): string => $record->is_admin ? 'Admin-rechten intrekken' : 'Admin maken')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->requiresConfirmation()
                        ->action(function (User $record): void {
                            // Je kunt je eigen admin-rechten niet intrekken: dan sluit je jezelf buiten.
                            if ($record->is(Auth::user()) && $record->is_admin) {
                                Notification::make()->title('Je kunt je eigen admin-rechten niet intrekken')->danger()->send();

                                return;
                            }

                            $record->forceFill(['is_admin' => ! $record->is_admin])->save();
                            ActivityLog::record($record->is_admin ? 'user.admin_granted' : 'user.admin_revoked', null, $record);

                            Notification::make()->title('Opgeslagen')->success()->send();
                        }),
                    Action::make('verifyEmail')->label('E-mail bevestigen')->icon(Heroicon::OutlinedCheckBadge)
                        ->visible(fn (User $record): bool => ! $record->hasVerifiedEmail())
                        ->requiresConfirmation()
                        ->action(function (User $record): void {
                            $record->markEmailAsVerified();
                            ActivityLog::record('user.email_verified', null, $record);

                            Notification::make()->title('E-mailadres bevestigd')->success()->send();
                        }),
                    Action::make('resetPassword')->label('Wachtwoord-reset sturen')->icon(Heroicon::OutlinedKey)
                        ->requiresConfirmation()
                        ->action(function (User $record): void {
                            $status = Password::sendResetLink(['email' => $record->email]);
                            ActivityLog::record('user.password_reset_sent', null, $record);

                            Notification::make()->title($status === Password::RESET_LINK_SENT ? 'Reset-link verstuurd' : 'Versturen is niet gelukt')
                                ->{$status === Password::RESET_LINK_SENT ? 'success' : 'danger'}()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListUsers::route('/')];
    }
}
