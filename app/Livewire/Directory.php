<?php

namespace App\Livewire;

use App\Models\Barbershop;
use App\Services\Geo\Geocoder;
use App\Services\ShopDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Kappersgids (fase 7): /kappers (best beoordeeld bij jou in de buurt) en /kappers/{stad} (per plaats, voor zoekmachines).
 * De locatie van de bezoeker wordt alleen voor deze zoekvraag gebruikt: niet opgeslagen en niet in de URL.
 */
class Directory extends Component
{
    #[Locked]
    public ?string $citySlug = null;

    public string $query = '';

    #[Locked]
    public ?float $lat = null;

    #[Locked]
    public ?float $lng = null;

    #[Locked]
    public string $placeLabel = '';

    public function mount(ShopDirectory $directory, ?string $city = null): void
    {
        if ($city !== null) {
            abort_unless(Barbershop::query()->listed()->where('city_slug', $city)->exists(), 404);

            $this->citySlug = $city;
        }
    }

    /**
     * Zoeken op postcode, adres of plaats.
     */
    public function search(Geocoder $geocoder): void
    {
        $this->validate(['query' => ['required', 'string', 'min:2', 'max:80']], messages: ['query.*' => 'Vul een postcode of plaats in.']);

        if (RateLimiter::tooManyAttempts('directory:'.request()->ip(), 30)) {
            $this->addError('query', 'Even geduld — probeer het over een minuut opnieuw.');

            return;
        }

        RateLimiter::hit('directory:'.request()->ip(), 60);

        $found = $geocoder->find($this->query);

        if ($found !== null) {
            $this->lat = $found['lat'];
            $this->lng = $found['lng'];
            $this->placeLabel = $found['label'];

            return;
        }

        // Geen coördinaten gevonden: kennen we de plaats zelf, ga dan naar de pagina van die plaats.
        $slug = Str::slug($this->query);

        if ($slug !== '' && Barbershop::query()->listed()->where('city_slug', $slug)->exists()) {
            $this->redirectRoute('directory.city', ['city' => $slug], navigate: true);

            return;
        }

        $this->addError('query', 'Die plaats of postcode kennen we niet. Probeer het opnieuw.');
    }

    /**
     * De bezoeker deelt zijn locatie (na een tik op de knop; de browser vraagt om toestemming).
     */
    public function locate(float $lat, float $lng): void
    {
        // Alleen binnen (ruim) Nederland.
        if ($lat < 50.5 || $lat > 53.8 || $lng < 3.0 || $lng > 7.4) {
            $this->addError('query', 'We vinden alleen kappers in Nederland. Vul een postcode of plaats in.');

            return;
        }

        $this->resetErrorBag();
        $this->lat = round($lat, 3);
        $this->lng = round($lng, 3);
        $this->placeLabel = 'jouw locatie';
        $this->query = '';
    }

    public function render(ShopDirectory $directory): View
    {
        $cities = $directory->cities();
        $city = $this->citySlug === null ? null : $cities->firstWhere('slug', $this->citySlug);
        $cityName = $city['name'] ?? ($this->citySlug === null ? null : Str::headline($this->citySlug));
        $searched = $this->lat !== null && $this->lng !== null;

        $shops = match (true) {
            $searched => $directory->near($this->lat, $this->lng),
            $this->citySlug !== null => $directory->inCity($this->citySlug),
            default => $directory->topRated(12),
        };

        $title = $cityName === null ? 'Vind een kapper bij jou in de buurt' : 'Kappers in '.$cityName;

        return view('livewire.directory', [
            'shops' => $shops,
            'cities' => $cities,
            'cityName' => $cityName,
            'searched' => $searched,
            'heading' => $title,
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $title,
                'itemListElement' => $shops->values()->map(fn (Barbershop $shop, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'item' => collect($shop->structuredData())->except('@context')->all(),
                ])->all(),
            ],
        ])->layout('layouts::platform', [
            'title' => $title,
            'description' => $cityName === null
                ? 'De best beoordeelde kappers en barbershops bij jou in de buurt. Reviews van echte klanten, direct online een afspraak maken.'
                : "De best beoordeelde kappers en barbershops in {$cityName}. Reviews van echte klanten, direct online een afspraak maken.",
        ]);
    }
}
