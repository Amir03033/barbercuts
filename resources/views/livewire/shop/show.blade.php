<div>
    @if ($bookingClosed)
        <x-ui.alert class="mx-4 mt-4">
            <p class="font-semibold">Online boeken is tijdelijk niet beschikbaar.</p>
            @if ($shop->phone)
                <p>Bel {{ $shop->name }} voor een afspraak:</p>
                <x-ui.button :href="'tel:'.$shop->phone" variant="secondary" size="sm" icon="phone" class="mt-2">{{ $shop->phone }}</x-ui.button>
            @endif
        </x-ui.alert>
    @endif

    <x-shop.page :shop="$shop" :services="$services" :staff-members="$staffMembers" :week="$week" :images="$images" :reviews="$reviews" :book-url="$bookUrl" />

    {{-- Voor zoekmachines: de zaak met adres en beoordeling (schema.org) --}}
    <script type="application/ld+json">{!! json_encode($shop->structuredData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
</div>
