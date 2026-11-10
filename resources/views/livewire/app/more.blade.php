<div>
    <x-ui.page-header title="Meer" :subtitle="$shop->name" />

    <div class="space-y-6 px-4 py-4">
        <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
            @if ($isOwner)
                <x-ui.list-item :href="route('app.services')" title="Diensten">
                    <x-slot:leading><x-ui.icon name="scissors" class="text-muted" /></x-slot:leading>
                </x-ui.list-item>
            @endif
            <x-ui.list-item :href="route('app.staff')" title="Kappers">
                <x-slot:leading><x-ui.icon name="users" class="text-muted" /></x-slot:leading>
            </x-ui.list-item>
            @if (! $isOwner && $ownStaffId)
                <x-ui.list-item :href="route('app.staff.edit', $ownStaffId)" title="Mijn werktijden">
                    <x-slot:leading><x-ui.icon name="clock" class="text-muted" /></x-slot:leading>
                </x-ui.list-item>
            @endif
            @if ($isOwner)
                <x-ui.list-item :href="route('app.opening-hours')" title="Openingstijden">
                    <x-slot:leading><x-ui.icon name="clock" class="text-muted" /></x-slot:leading>
                </x-ui.list-item>
                <x-ui.list-item :href="route('app.appearance')" title="Uiterlijk">
                    <x-slot:leading><x-ui.icon name="palette" class="text-muted" /></x-slot:leading>
                </x-ui.list-item>
                <x-ui.list-item :href="route('app.settings')" title="Instellingen">
                    <x-slot:leading><x-ui.icon name="settings" class="text-muted" /></x-slot:leading>
                </x-ui.list-item>
            @endif
        </x-ui.card>

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
    </div>
</div>
