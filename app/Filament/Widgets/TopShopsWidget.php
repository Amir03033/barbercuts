<?php

namespace App\Filament\Widgets;

use App\Models\Barbershop;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopShopsWidget extends TableWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Zaken met de meeste boekingen')
            ->query(fn () => Barbershop::query()->withCount('appointments')->orderByDesc('appointments_count')->limit(10))
            ->columns([
                TextColumn::make('name')->label('Zaak'),
                TextColumn::make('city')->label('Plaats')->placeholder('-'),
                TextColumn::make('appointments_count')->label('Afspraken'),
            ])
            ->paginated(false);
    }
}
