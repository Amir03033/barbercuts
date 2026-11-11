<?php

namespace App\Filament\Widgets;

use App\Models\Barbershop;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class TopCitiesWidget extends TableWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Steden met de meeste zaken')
            // Een afgeleide tabel: Filament sorteert altijd ook op de sleutel, en dat mag niet botsen met GROUP BY.
            ->query(fn () => Barbershop::query()
                ->fromSub(DB::table('barbershops')->whereNotNull('city')->selectRaw('MIN(id) as id, city, COUNT(*) as shops_count')->groupBy('city'), 'barbershops')
                ->orderByDesc('shops_count')->orderBy('city')->limit(10))
            ->columns([
                TextColumn::make('city')->label('Plaats'),
                TextColumn::make('shops_count')->label('Zaken'),
            ])
            ->paginated(false);
    }
}
