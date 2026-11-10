<div>
    <x-ui.page-header :title="$editing ? 'Dienst bewerken' : 'Nieuwe dienst'" :back="route('app.services')" />

    <form wire:submit="save" class="space-y-4 px-4 py-4" novalidate>
        <x-ui.input label="Naam" wire:model="name" placeholder="Bijvoorbeeld: Knippen" autocomplete="off" required />
        <div class="grid grid-cols-2 gap-3">
            <x-ui.input label="Duur (minuten)" type="text" inputmode="numeric" wire:model="duration" required />
            <x-ui.money-input label="Prijs" wire:model="price" />
        </div>
        <x-ui.select label="Uitloop na de afspraak" wire:model="bufferAfter" :selected="$bufferAfter"
            :options="[0 => 'Geen', 5 => '5 minuten', 10 => '10 minuten', 15 => '15 minuten', 30 => '30 minuten']"
            hint="Tijd om op te ruimen. In die tijd kan niemand boeken." />
        <x-ui.textarea label="Omschrijving" wire:model="description" optional />

        @if ($staffMembers->count() > 1)
            <x-ui.field label="Wie doet deze dienst?" name="staffIds">
                <div class="flex flex-wrap gap-2">
                    @foreach ($staffMembers as $staff)
                        <x-ui.chip multiple wire:model="staffIds" :value="$staff->id" wire:key="staff-{{ $staff->id }}">{{ $staff->name }}</x-ui.chip>
                    @endforeach
                </div>
            </x-ui.field>
        @endif

        <x-ui.card flat>
            <x-ui.toggle label="Actief" hint="Niet actief: klanten kunnen deze dienst niet boeken." wire:model="isActive" :checked="$isActive" />
        </x-ui.card>

        @if ($editing)
            <x-ui.button variant="ghost" icon="trash" full wire:click="delete" wire:confirm="Weet je zeker dat je deze dienst wilt verwijderen?">Dienst verwijderen</x-ui.button>
        @endif

        <x-ui.sticky-footer nav>
            <x-ui.button type="submit" loading="save" full>Opslaan</x-ui.button>
        </x-ui.sticky-footer>
    </form>
</div>
