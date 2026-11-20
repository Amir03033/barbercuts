<?php

namespace App\Filament\Resources\PromoVideos;

use App\Filament\Resources\PromoVideos\Pages\CreatePromoVideo;
use App\Filament\Resources\PromoVideos\Pages\EditPromoVideo;
use App\Filament\Resources\PromoVideos\Pages\ListPromoVideos;
use App\Models\PromoVideo;
use App\Support\ImageStore;
use App\Support\UploadLimit;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Promovideo's uploaden en kiezen waar ze verschijnen: demopagina, landingspagina en/of bij de pitchers om te delen.
 */
class PromoVideoResource extends Resource
{
    /** Grootste video in kilobytes (100 MB). De server moet uploads van die grootte toestaan (upload_max_filesize). */
    public const int MAX_KB = 102400;

    protected static ?string $model = PromoVideo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = "Video's";

    protected static ?string $modelLabel = 'video';

    protected static ?string $pluralModelLabel = "video's";

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Video')->columnSpanFull()->schema([
                TextInput::make('title')->label('Titel')->required()->maxLength(120)
                    ->helperText('Voor jezelf en als bestandsnaam bij downloaden, bijvoorbeeld "Agenda in 30 seconden".'),
                TextInput::make('caption')->label('Tekst eronder')->maxLength(255)->placeholder('Bijv. Zo boeken je klanten zelf een afspraak')
                    ->helperText('Optioneel. Staat onder de video op de site.'),
                FileUpload::make('file_path')->label('Videobestand')->required()
                    ->disk(ImageStore::disk())->directory('videos')->visibility('public')
                    ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])
                    ->maxSize(self::maxKilobytes())
                    ->helperText(self::uploadHelp()),
                FileUpload::make('poster_path')->label('Voorbeeldplaatje')->image()
                    ->disk(ImageStore::disk())->directory('videos/posters')->visibility('public')->maxSize(4096)
                    ->helperText('Optioneel. Wat je ziet voordat de video speelt. Zonder plaatje toont de browser het eerste beeld.'),
                Radio::make('orientation')->label('Formaat')->options(PromoVideo::ORIENTATIONS)->default('landscape')->required()->inline(),
            ]),
            Section::make('Waar verschijnt hij?')->columnSpanFull()->schema([
                Toggle::make('show_on_demo')->label('Op de demopagina (/probeer)')->helperText('Boven de startknop: wie niet wil klikken, ziet toch hoe het werkt.'),
                Toggle::make('show_on_home')->label('Op de landingspagina')->helperText('In het blok "Zo werkt het".'),
                Toggle::make('for_pitchers')->label('Bij de pitchers om te delen')->default(true)->helperText('In hun dashboard, met downloaden en delen via WhatsApp of Instagram.'),
                Toggle::make('is_active')->label('Actief')->default(true),
                TextInput::make('sort_order')->label('Volgorde')->numeric()->integer()->minValue(0)->default(0)->helperText('Laagste eerst.'),
            ]),
        ]);
    }

    /**
     * De grootste video die nu kan: 100 MB, of minder als de server (php.ini) minder toestaat.
     */
    public static function maxKilobytes(): int
    {
        $server = UploadLimit::kilobytes();

        return $server > 0 ? min(self::MAX_KB, $server) : self::MAX_KB;
    }

    public static function uploadHelp(): string
    {
        $help = 'MP4 (H.264) werkt overal het best. Liefst onder 15 MB, dan laadt hij snel op een telefoon.';
        $server = UploadLimit::kilobytes();

        if ($server > 0 && $server < 30 * 1024) {
            return $help.' Let op: deze server accepteert nu uploads tot '.UploadLimit::label($server).'. Zet in Plesk (PHP-instellingen) upload_max_filesize en post_max_size op 128M voor grotere video\'s.';
        }

        return $help.' Hooguit '.UploadLimit::label(self::maxKilobytes()).'.';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')->label('Titel')->searchable()->description(fn (PromoVideo $record): ?string => $record->caption),
                TextColumn::make('orientation')->label('Formaat')->formatStateUsing(fn (string $state): string => PromoVideo::ORIENTATIONS[$state] ?? $state),
                IconColumn::make('show_on_demo')->label('Demo')->boolean(),
                IconColumn::make('show_on_home')->label('Landing')->boolean(),
                IconColumn::make('for_pitchers')->label('Pitchers')->boolean(),
                IconColumn::make('is_active')->label('Actief')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label('Bewerken'),
                DeleteAction::make()->label('Verwijderen'),
            ])
            ->emptyStateHeading("Nog geen video's")
            ->emptyStateDescription('Upload je motion graphics en kies waar ze verschijnen.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromoVideos::route('/'),
            'create' => CreatePromoVideo::route('/create'),
            'edit' => EditPromoVideo::route('/{record}/edit'),
        ];
    }
}
