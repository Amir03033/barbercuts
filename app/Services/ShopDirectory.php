<?php

namespace App\Services;

use App\Models\Barbershop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * De kappersgids (fase 7): best beoordeelde zaken in de buurt, per plaats en landelijk.
 * Alleen echte, publieke zaken (Barbershop::listed()); demozaken komen hier nooit in.
 */
class ShopDirectory
{
    public const int RADIUS_KM = 15;

    /**
     * Landelijk best beoordeeld: alleen zaken met genoeg reviews.
     *
     * @return Collection<int, Barbershop>
     */
    public function topRated(int $limit = 6): Collection
    {
        return $this->ranked(Barbershop::query()->listed()->where('rating_count', '>=', ReviewService::MIN_REVIEWS_FOR_TOP))->limit($limit)->get();
    }

    /**
     * Zaken binnen de straal, best beoordeeld eerst en daarna op afstand. Elke zaak krijgt `distance_km`.
     *
     * @return Collection<int, Barbershop>
     */
    public function near(float $lat, float $lng, int $radiusKm = self::RADIUS_KM, int $limit = 40): Collection
    {
        // Afstand over de bol (haversine), in kilometers.
        $distance = '6371 * acos(least(1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

        return Barbershop::query()->listed()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->select('barbershops.*')
            ->selectRaw("{$distance} as distance_km", [$lat, $lng, $lat])
            ->whereRaw("{$distance} <= ?", [$lat, $lng, $lat, $radiusKm])
            ->orderByDesc('rating_score')->orderBy('distance_km')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Barbershop>
     */
    public function inCity(string $citySlug, int $limit = 60): Collection
    {
        return $this->ranked(Barbershop::query()->listed()->where('city_slug', $citySlug))->limit($limit)->get();
    }

    /**
     * Plaatsen met minstens één zaak, grootste eerst.
     *
     * @return SupportCollection<int, array{slug: string, name: string, shops: int}>
     */
    public function cities(int $limit = 40): SupportCollection
    {
        return Barbershop::query()->listed()->whereNotNull('city_slug')
            ->groupBy('city_slug')
            ->selectRaw('city_slug as slug, MIN(city) as name, COUNT(*) as shops')
            ->orderByDesc('shops')->orderBy('slug')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => ['slug' => (string) $row->slug, 'name' => (string) $row->name, 'shops' => (int) $row->shops]);
    }

    /**
     * @param  Builder<Barbershop>  $query
     * @return Builder<Barbershop>
     */
    private function ranked(Builder $query): Builder
    {
        return $query->orderByDesc('rating_score')->orderByDesc('rating_count')->orderBy('name');
    }
}
