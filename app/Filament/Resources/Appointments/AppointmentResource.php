<?php

namespace App\Filament\Resources\Appointments;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Models\Appointment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Afspraken van alle zaken, alleen lezen, voor support (§11).
 */
class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Afspraken';

    protected static ?string $modelLabel = 'afspraak';

    protected static ?string $pluralModelLabel = 'afspraken';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['barbershop', 'customer', 'staffMember']))
            ->columns([
                TextColumn::make('starts_at')->label('Tijd')->sortable()
                    ->state(fn (Appointment $record): string => $record->localStart()->format('d-m-Y H:i')),
                TextColumn::make('barbershop.name')->label('Zaak')->searchable(),
                TextColumn::make('customer.name')->label('Klant')->searchable(),
                TextColumn::make('staffMember.name')->label('Kapper'),
                TextColumn::make('service_name')->label('Dienst'),
                TextColumn::make('price_cents')->label('Prijs')->formatStateUsing(fn (int $state): string => money($state)),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (AppointmentStatus $state): string => match ($state) {
                        AppointmentStatus::Scheduled => 'Gepland',
                        AppointmentStatus::Completed => 'Voltooid',
                        AppointmentStatus::Cancelled => 'Geannuleerd',
                        AppointmentStatus::NoShow => 'No-show',
                    })
                    ->color(fn (AppointmentStatus $state): string => match ($state) {
                        AppointmentStatus::Completed => 'success',
                        AppointmentStatus::Cancelled => 'gray',
                        AppointmentStatus::NoShow => 'danger',
                        AppointmentStatus::Scheduled => 'info',
                    }),
                TextColumn::make('source')->label('Bron')->formatStateUsing(fn (AppointmentSource $state): string => match ($state) {
                    AppointmentSource::Online => 'Online',
                    AppointmentSource::Staff => 'Kapper',
                    AppointmentSource::WalkIn => 'Inloop',
                })->toggleable(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('barbershop_id')->label('Zaak')->relationship('barbershop', 'name')->searchable()->preload(),
                SelectFilter::make('status')->label('Status')->options(collect(AppointmentStatus::cases())->mapWithKeys(fn (AppointmentStatus $s): array => [$s->value => ucfirst($s->value)])->all()),
                SelectFilter::make('source')->label('Bron')->options(collect(AppointmentSource::cases())->mapWithKeys(fn (AppointmentSource $s): array => [$s->value => ucfirst($s->value)])->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAppointments::route('/')];
    }
}
