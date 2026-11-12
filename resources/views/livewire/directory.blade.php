<div class="mx-auto max-w-3xl px-4 py-8 md:py-12">
    <h1 class="text-lg font-bold tracking-tight md:text-display">{{ $heading }}</h1>
    <p class="mt-2 text-sm text-muted">
        Best beoordeeld bovenaan. Reviews komen alleen van klanten die er echt geweest zijn.
    </p>

    <form wire:submit="search" class="mt-6 space-y-3" novalidate
        x-data="{ busy: false, failed: false, locate() {
            if (! navigator.geolocation) { this.failed = true; return; }
            this.busy = true; this.failed = false;
            navigator.geolocation.getCurrentPosition(
                (position) => $wire.locate(position.coords.latitude, position.coords.longitude).finally(() => this.busy = false),
                () => { this.busy = false; this.failed = true; },
                { maximumAge: 300000, timeout: 10000 }
            );
        } }">
        <x-ui.input label="Postcode of plaats" wire:model="query" autocomplete="postal-code" enterkeyhint="search" placeholder="Bijv. 3511 AB of Utrecht" />
        <div class="flex flex-col gap-3 sm:flex-row">
            <x-ui.button type="submit" loading="search" icon="search">Zoeken</x-ui.button>
            <x-ui.button variant="secondary" icon="map-pin" x-on:click="locate()" x-bind:disabled="busy">Gebruik mijn locatie</x-ui.button>
        </div>
        <p x-show="failed" x-cloak class="text-sm text-muted">Je locatie ophalen lukte niet. Vul een postcode of plaats in.</p>
    </form>

    <h2 class="mt-10 mb-3 text-base font-semibold">
        @if ($searched)
            Kappers in de buurt van {{ $placeLabel }}
        @elseif ($cityName)
            Best beoordeeld in {{ $cityName }}
        @else
            Best beoordeeld in Nederland
        @endif
    </h2>

    @if ($shops->isEmpty())
        <x-ui.empty-state icon="map-pin" class="rounded-card border border-border bg-card"
            :title="$searched ? 'Nog geen kappers in deze buurt' : 'Nog geen beoordeelde kappers'"
            :text="$searched ? 'Binnen '.\App\Services\ShopDirectory::RADIUS_KM.' km vonden we niets. Probeer een andere plaats.' : 'Zoek op postcode of plaats om kappers bij jou in de buurt te vinden.'" />
    @else
        <div class="space-y-3">
            @foreach ($shops as $shop)
                <x-platform.shop-card :shop="$shop" wire:key="shop-{{ $shop->id }}" />
            @endforeach
        </div>
    @endif

    @if ($cities->isNotEmpty())
        <h2 class="mt-10 mb-3 text-base font-semibold">Kappers per plaats</h2>
        <div class="flex flex-wrap gap-2">
            @foreach ($cities as $city)
                <x-ui.chip :href="route('directory.city', $city['slug'])" :active="$city['slug'] === $citySlug">{{ $city['name'] }}</x-ui.chip>
            @endforeach
        </div>
    @endif

    <p class="mt-10 text-sm text-muted">
        Heb je zelf een kapperszaak? <a href="{{ route('onboarding') }}" class="font-semibold text-text underline">Meld je zaak aan</a> en sta hier ook tussen.
    </p>

    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
</div>
