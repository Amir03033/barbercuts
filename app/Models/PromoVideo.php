<?php

namespace App\Models;

use App\Support\ImageStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Een promovideo (motion graphic van ±30 seconden). De beheerder kiest waar hij verschijnt: op de demopagina
 * (/probeer), op de landingspagina en/of bij de pitchers om te delen. Bestanden staan op dezelfde schijf als de
 * foto's van zaken (ImageStore::disk()) in de map videos/.
 *
 * @property string $title
 * @property string|null $caption
 * @property string $file_path
 * @property string|null $poster_path
 * @property string $orientation landscape | portrait | square
 * @property bool $show_on_demo
 * @property bool $show_on_home
 * @property bool $for_pitchers
 * @property bool $is_active
 * @property int $sort_order
 */
class PromoVideo extends Model
{
    public const array ORIENTATIONS = [
        'landscape' => 'Liggend (16:9)',
        'portrait' => 'Staand (9:16, voor stories)',
        'square' => 'Vierkant (1:1)',
    ];

    protected $fillable = ['title', 'caption', 'file_path', 'poster_path', 'orientation', 'show_on_demo', 'show_on_home', 'for_pitchers', 'is_active', 'sort_order'];

    protected static function booted(): void
    {
        // Bestanden opruimen als een video weg gaat of een nieuw bestand krijgt.
        static::deleted(function (PromoVideo $video): void {
            Storage::disk(ImageStore::disk())->delete(array_filter([$video->file_path, $video->poster_path]));
        });

        static::updated(function (PromoVideo $video): void {
            foreach (['file_path', 'poster_path'] as $column) {
                $old = $video->getOriginal($column);

                if ($video->wasChanged($column) && is_string($old) && $old !== '') {
                    Storage::disk(ImageStore::disk())->delete($old);
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_on_demo' => 'boolean',
            'show_on_home' => 'boolean',
            'for_pitchers' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<PromoVideo>  $query
     * @return Builder<PromoVideo>
     */
    public function scopeShown(Builder $query, string $place): Builder
    {
        return $query->where('is_active', true)->where($place, true)->orderBy('sort_order')->orderByDesc('id');
    }

    public function url(): string
    {
        return (string) ImageStore::url($this->file_path);
    }

    public function posterUrl(): ?string
    {
        return ImageStore::url($this->poster_path);
    }

    /**
     * De verhouding als CSS aspect-ratio.
     */
    public function aspectRatio(): string
    {
        return match ($this->orientation) {
            'portrait' => '9 / 16',
            'square' => '1 / 1',
            default => '16 / 9',
        };
    }

    public function downloadName(): string
    {
        return str($this->title)->slug()->append('.'.pathinfo($this->file_path, PATHINFO_EXTENSION))->toString();
    }
}
