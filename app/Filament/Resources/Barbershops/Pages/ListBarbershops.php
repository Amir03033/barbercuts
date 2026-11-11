<?php

namespace App\Filament\Resources\Barbershops\Pages;

use App\Filament\Resources\Barbershops\BarbershopResource;
use Filament\Resources\Pages\ListRecords;

class ListBarbershops extends ListRecords
{
    protected static string $resource = BarbershopResource::class;
}
