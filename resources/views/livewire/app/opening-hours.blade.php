<div>
    <x-ui.page-header title="Openingstijden" :back="route('app.more')" />

    <div class="space-y-8 px-4 py-4">
        <x-ui.section text="Dit zien klanten op je pagina. Buiten deze tijden kan niemand boeken, ook niet als een kapper langer werkt.">
            <form wire:submit="save" class="space-y-3" novalidate>
                <x-ui.week-hours :week="$week" :errors="$weekErrors" />
                <x-ui.button type="submit" loading="save" full>Openingstijden opslaan</x-ui.button>
            </form>
        </x-ui.section>

        <x-ui.section title="Gesloten op…" text="Feestdagen of vakantie: de hele zaak is dicht.">
            <x-ui.time-off-list :items="$closures" :timezone="$timezone" empty="Geen sluitingsdagen gepland." />
            <x-ui.button variant="secondary" icon="plus" full x-on:click="$dispatch('open-sheet', 'closure')">Sluitingsdag toevoegen</x-ui.button>
        </x-ui.section>
    </div>

    <x-ui.bottom-sheet name="closure" title="Gesloten op…">
        <form wire:submit="addClosure" class="space-y-4" novalidate>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.input label="Van" type="date" wire:model="closedFrom" />
                <x-ui.input label="Tot en met" type="date" wire:model="closedTo" optional />
            </div>
            <x-ui.input label="Reden" wire:model="closedReason" optional placeholder="Bijvoorbeeld: Koningsdag" />
            <x-ui.button type="submit" loading="addClosure" full>Toevoegen</x-ui.button>
        </form>
    </x-ui.bottom-sheet>
</div>
