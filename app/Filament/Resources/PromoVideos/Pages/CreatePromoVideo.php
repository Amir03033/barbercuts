<?php

namespace App\Filament\Resources\PromoVideos\Pages;

use App\Filament\Resources\PromoVideos\PromoVideoResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromoVideo extends CreateRecord
{
    protected static string $resource = PromoVideoResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
