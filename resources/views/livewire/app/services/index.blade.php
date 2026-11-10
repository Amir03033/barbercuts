<div>
    <x-ui.page-header title="Diensten" :back="route('app.more')" />

    <div class="px-4 py-4">
        @if ($services->isEmpty())
            <x-ui.empty-state icon="scissors" title="Nog geen diensten" text="Voeg je eerste dienst toe, dan kunnen klanten online boeken." />
        @else
            <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                @foreach ($services as $service)
                    <x-ui.list-item :href="route('app.services.edit', $service)" :title="$service->name"
                        :subtitle="duration_label($service->duration).($service->is_active ? '' : ' · niet actief')" wire:key="service-{{ $service->id }}">
                        <x-slot:trailing><span class="font-semibold tabular-nums">{{ money($service->price_cents) }}</span></x-slot:trailing>
                    </x-ui.list-item>
                @endforeach
            </x-ui.card>
        @endif
    </div>

    <x-ui.sticky-footer nav>
        <x-ui.button full icon="plus" :href="route('app.services.create')" wire:navigate>Dienst toevoegen</x-ui.button>
    </x-ui.sticky-footer>
</div>
