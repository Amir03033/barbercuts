<?php

namespace App\Filament\Resources\PromoVideos\Pages;

use App\Filament\Resources\PromoVideos\PromoVideoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPromoVideo extends EditRecord
{
    protected static string $resource = PromoVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
