<div>
    <x-ui.page-header title="Meer" :subtitle="$shop->name" />

    <div class="space-y-6 px-4 py-4">
        @php
            $billing = \App\Support\Features::billing();
            $groups = [
                'Je zaak' => array_filter([
                    ['route' => 'app.revenue', 'title' => $isOwner ? 'Omzet' : 'Mijn omzet', 'icon' => 'trending-up', 'color' => '#34c759'],
                    ['route' => 'app.reviews', 'title' => 'Reviews', 'icon' => 'star', 'color' => '#ff9f0a', 'subtitle' => $shop->hasRating() ? number_format($shop->rating_avg, 1, ',', '').' · '.$shop->rating_count.' reviews' : null],
                    ['route' => 'app.waitlist', 'title' => 'Wachtlijst', 'icon' => 'clock', 'color' => '#5e5ce6'],
                    $isOwner ? ['route' => 'app.winback', 'title' => 'Klanten terughalen', 'icon' => 'heart', 'color' => '#ff375f'] : null,
                    $isOwner ? ['route' => 'app.loyalty', 'title' => 'Spaarkaart', 'icon' => 'gift', 'color' => '#bf5af2'] : null,
                ]),
                'Beheer' => array_filter([
                    $isOwner ? ['route' => 'app.services', 'title' => 'Diensten', 'icon' => 'scissors', 'color' => '#0071e3'] : null,
                    ['route' => 'app.staff', 'title' => 'Kappers', 'icon' => 'users', 'color' => '#30b0c7'],
                    $isOwner ? ['route' => 'app.opening-hours', 'title' => 'Openingstijden', 'icon' => 'clock', 'color' => '#ff9f0a'] : null,
                    $isOwner ? ['route' => 'app.appearance', 'title' => 'Uiterlijk', 'icon' => 'palette', 'color' => '#ff375f', 'subtitle' => "Kleuren, foto's en banner"] : null,
                    $isOwner ? ['route' => 'app.settings', 'title' => 'Instellingen', 'icon' => 'settings', 'color' => '#8e8e93'] : null,
                    $isOwner && ! $shop->is_demo ? ['route' => 'app.subscription', 'title' => 'Abonnement', 'icon' => 'credit-card', 'color' => '#1d1d1f', 'subtitle' => $billing ? $shop->accessState()->label() : 'Betalen staat tijdelijk uit'] : null,
                ]),
                'Jij' => array_filter([
                    ['route' => 'app.profile', 'title' => 'Mijn profiel', 'icon' => 'user', 'color' => '#8e8e93'],
                    ! $isOwner && $ownStaffId ? ['href' => route('app.staff.edit', $ownStaffId), 'title' => 'Mijn werktijden', 'icon' => 'clock', 'color' => '#ff9f0a'] : null,
                ]),
            ];
        @endphp

        @foreach ($groups as $group => $items)
            <x-ui.section :title="$group">
                <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                    @foreach ($items as $item)
                        <x-ui.list-item :href="$item['href'] ?? route($item['route'])" :title="$item['title']" :subtitle="$item['subtitle'] ?? null">
                            <x-slot:leading><x-ui.icon-tile :name="$item['icon']" :color="$item['color']" /></x-slot:leading>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.card>
            </x-ui.section>
        @endforeach

        <x-ui.section title="Boekingslink delen">
            <x-ui.copy-link :url="$shop->bookingUrl()" :label="$shop->bookingUrlLabel()" />
            <div class="grid grid-cols-2 gap-3">
                <x-ui.button variant="secondary" icon="message-circle" target="_blank" rel="noopener"
                    :href="'https://wa.me/?text='.rawurlencode('Maak je afspraak bij '.$shop->name.': '.$shop->bookingUrl())">WhatsApp</x-ui.button>
                <x-ui.button variant="secondary" icon="qr" :href="route('app.qr')" download>QR-code</x-ui.button>
            </div>
        </x-ui.section>

        @if ($otherShops->isNotEmpty())
            <x-ui.section title="Andere zaak">
                <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                    @foreach ($otherShops as $other)
                        <x-ui.list-item :title="$other->name" :subtitle="$other->city" wire:key="shop-{{ $other->id }}" :chevron="false">
                            <x-slot:trailing>
                                <x-ui.button variant="secondary" size="sm" wire:click="switchShop({{ $other->id }})">Open</x-ui.button>
                            </x-slot:trailing>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.card>
            </x-ui.section>
        @endif

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" icon="log-out" full>Uitloggen</x-ui.button>
        </form>

        <p class="pb-2 text-center text-xs text-muted"><x-credit /></p>
    </div>
</div>
