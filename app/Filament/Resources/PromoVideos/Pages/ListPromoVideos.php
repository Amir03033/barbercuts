<?php

namespace App\Filament\Resources\PromoVideos\Pages;

use App\Filament\Resources\PromoVideos\PromoVideoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromoVideos extends ListRecords
{
    protected static string $resource = PromoVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Video uploaden'),
        ];
    }
}
