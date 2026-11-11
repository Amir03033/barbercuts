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

    <x-shop.page :shop="$shop" :services="$services" :staff-members="$staffMembers" :week="$week" :images="$images" :book-url="$bookUrl" />
</div>
