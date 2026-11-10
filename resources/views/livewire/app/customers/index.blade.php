<div>
    <x-ui.page-header title="Klanten" :subtitle="$total === 1 ? '1 klant' : $total.' klanten'" />

    <div class="sticky top-14 z-10 border-b border-border bg-bg/95 px-4 py-3 backdrop-blur">
        <x-ui.input type="search" wire:model.live.debounce.250ms="search" aria-label="Zoek op naam of telefoonnummer" placeholder="Zoek op naam of telefoonnummer" autocomplete="off" />
    </div>

    <div class="px-4 py-4">
        @if ($customers->isEmpty())
            <x-ui.empty-state icon="users" :title="$search === '' ? 'Nog geen klanten' : 'Niemand gevonden'"
                :text="$search === '' ? 'Klanten verschijnen hier zodra ze boeken, of voeg er een toe bij een nieuwe afspraak.' : 'Probeer een andere naam of een deel van het telefoonnummer.'">
                <x-ui.button :href="route('app.appointments.create')" variant="secondary" icon="plus" wire:navigate>Nieuwe afspraak</x-ui.button>
            </x-ui.empty-state>
        @else
            <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                @foreach ($customers as $customer)
                    <x-ui.list-item :href="route('app.customers.show', $customer)" wire:key="customer-{{ $customer->id }}" :title="$customer->name"
                        :subtitle="$customer->last_visit_at ? 'Laatste bezoek '.mb_strtolower(human_date($customer->last_visit_at, $shop->timezone)) : 'Nog niet geweest'">
                        <x-slot:leading><x-ui.avatar :name="$customer->name" /></x-slot:leading>
                    </x-ui.list-item>
                @endforeach
            </x-ui.card>

            @if ($customers->count() < $total)
                <div class="mt-4">
                    <x-ui.button variant="secondary" full wire:click="loadMore" loading="loadMore">Meer laden</x-ui.button>
                </div>
            @endif
        @endif
    </div>
</div>
