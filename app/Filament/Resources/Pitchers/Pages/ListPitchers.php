<?php

namespace App\Filament\Resources\Pitchers\Pages;

use App\Filament\Resources\Pitchers\PitcherResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPitchers extends ListRecords
{
    protected static string $resource = PitcherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')->label('Pitcher toevoegen')
                ->schema([
                    TextInput::make('name')->label('Naam')->required()->maxLength(60),
                    TextInput::make('email')->label('E-mailadres')->email()->required()->maxLength(255)
                        ->helperText('Bestaat er al een account met dit adres, dan krijgt dat account toegang tot de demo.'),
                ])
                ->modalSubmitActionLabel('Uitnodigen')
                ->action(function (array $data): void {
                    PitcherResource::invite(trim($data['name']), mb_strtolower(trim($data['email'])));

                    Notification::make()->title('Pitcher toegevoegd en uitgenodigd')->success()->send();
                }),
        ];
    }
}
