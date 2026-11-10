@php
    $section = 'mb-3 mt-10 text-xs font-semibold tracking-wide text-muted uppercase';
@endphp

<div class="mx-auto max-w-2xl px-4 pb-8">
    @if ($layout !== 'platform')
        <x-ui.page-header title="UI-showcase" :back="route('ui')" subtitle="layouts.{{ $layout }}" class="-mx-4">
            <x-ui.button variant="ghost" size="sm" x-on:click="$dispatch('toast', 'Actie rechtsboven')">Actie</x-ui.button>
        </x-ui.page-header>
    @else
        <h1 class="pt-6 text-lg font-bold">UI-showcase</h1>
        <p class="text-sm text-muted">Alle x-ui componenten. Controleer op 375px breed.</p>
    @endif

    <h2 class="{{ $section }}">Layouts</h2>
    <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4">
        <x-ui.chip :href="route('ui')" :active="$layout === 'platform'">platform</x-ui.chip>
        <x-ui.chip :href="route('ui', 'app')" :active="$layout === 'app'">app</x-ui.chip>
        <x-ui.chip :href="route('ui', ['layout' => 'shop'])" :active="$layout === 'shop' && $theme === 'light'">shop licht</x-ui.chip>
        <x-ui.chip :href="route('ui', ['layout' => 'shop', 'theme' => 'dark', 'accent' => '#facc15'])" :active="$layout === 'shop' && $theme === 'dark'">shop donker</x-ui.chip>
    </div>

    <h2 class="{{ $section }}">Merk</h2>
    <x-brand />

    <h2 class="{{ $section }}">Knoppen</h2>
    <div class="space-y-3">
        <x-ui.button full>Primary</x-ui.button>
        <x-ui.button variant="secondary" full icon="calendar">Secondary met icoon</x-ui.button>
        <div class="flex flex-wrap gap-3">
            <x-ui.button variant="ghost">Ghost</x-ui.button>
            <x-ui.button variant="danger">Danger</x-ui.button>
            <x-ui.button :loading="true">Bezig</x-ui.button>
            <x-ui.button disabled>Uit</x-ui.button>
        </div>
    </div>

    <h2 class="{{ $section }}">Formulier</h2>
    <form wire:submit="save" class="space-y-4" novalidate>
        <x-ui.input label="Naam" wire:model="name" autocomplete="name" placeholder="Voor- en achternaam" />
        <x-ui.phone-input wire:model="phone" />
        <x-ui.input label="E-mailadres" type="email" name="email" autocomplete="email" optional hint="Voor je bevestiging per mail." />
        <x-ui.money-input label="Bedrag" wire:model="amount" />
        <x-ui.select label="Dienst" name="service" placeholder="Kies een dienst"
            :options="['cut' => 'Knippen · 30 min · € 25,00', 'beard' => 'Baard · 15 min · € 15,00']" />
        <x-ui.textarea label="Opmerking" name="notes" optional placeholder="Bijvoorbeeld: fade 1,5 aan de zijkant" />
        <x-ui.button type="submit" loading="save" full>Opslaan (valideert en toont een toast)</x-ui.button>
    </form>

    <h2 class="{{ $section }}">Segmented</h2>
    <x-ui.segmented label="Periode" wire:model.live="period" :value="$period"
        :options="['day' => 'Dag', 'week' => 'Week', 'month' => 'Maand', 'year' => 'Jaar']" />
    <p class="mt-2 text-xs text-muted">Gekozen: {{ $period }}</p>

    <h2 class="{{ $section }}">Stats</h2>
    <div class="grid grid-cols-2 gap-3">
        <x-ui.stat label="Omzet vandaag" :value="money(48250)" delta="+12%" trend="up" hint="t.o.v. vorige week" />
        <x-ui.stat label="Afspraken" value="14" delta="-2" trend="down" />
    </div>

    <h2 class="{{ $section }}">Chips</h2>
    <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4">
        <x-ui.chip wire:model.live="staff" value="any" :checked="$staff === 'any'">Geen voorkeur</x-ui.chip>
        <x-ui.chip wire:model.live="staff" value="sam" :checked="$staff === 'sam'">Sam</x-ui.chip>
        <x-ui.chip wire:model.live="staff" value="mo" :checked="$staff === 'mo'">Mo</x-ui.chip>
        <x-ui.chip name="staff-disabled" value="x" disabled>Vol</x-ui.chip>
    </div>

    <h2 class="{{ $section }}">Datumstrip</h2>
    <x-ui.date-strip :days="$days" wire:model.live="date" :selected="$date" />

    <h2 class="{{ $section }}">Tijden</h2>
    <x-ui.time-grid wire:model.live="time" :selected="$time" :times="[
        'Ochtend' => ['09:00', '09:15', '10:30', '11:45'],
        'Middag' => ['13:00', '13:15', '14:30', '15:00', '16:15'],
        'Avond' => ['18:00', '18:30'],
    ]" />
    <p class="mt-2 text-xs text-muted">Gekozen: {{ $date }} om {{ $time }} — {{ human_date(\Carbon\CarbonImmutable::parse($date, 'Europe/Amsterdam')) }}</p>

    <h2 class="{{ $section }}">Kaart met lijst</h2>
    <x-ui.card :padding="false" class="divide-y divide-border overflow-hidden">
        <x-ui.list-item href="#" title="Daan de Vries" subtitle="Knippen · 14:30 – 15:00">
            <x-slot:leading><x-ui.avatar name="Daan de Vries" color="#2563eb" /></x-slot:leading>
            <x-slot:trailing><x-ui.badge status="scheduled" /></x-slot:trailing>
        </x-ui.list-item>
        <x-ui.list-item href="#" title="Yusuf Kaya" subtitle="Knippen + baard · 15:15 – 16:00">
            <x-slot:leading><x-ui.avatar name="Yusuf Kaya" /></x-slot:leading>
            <x-slot:trailing><x-ui.badge status="completed" /></x-slot:trailing>
        </x-ui.list-item>
        <x-ui.list-item title="Lars" subtitle="Baard · 16:00 – 16:15" :chevron="false">
            <x-slot:leading><x-ui.avatar name="Lars" size="md" color="#16a34a" /></x-slot:leading>
            <x-slot:trailing><span class="font-semibold tabular-nums">{{ money(1500) }}</span></x-slot:trailing>
        </x-ui.list-item>
    </x-ui.card>

    <h2 class="{{ $section }}">Badges</h2>
    <div class="flex flex-wrap gap-2">
        <x-ui.badge status="scheduled" />
        <x-ui.badge status="completed" />
        <x-ui.badge status="cancelled" />
        <x-ui.badge status="no_show" />
        <x-ui.badge status="neutral">Proefperiode</x-ui.badge>
    </div>

    <h2 class="{{ $section }}">Avatars</h2>
    <div class="flex items-center gap-3">
        <x-ui.avatar name="Sam Bakker" size="sm" color="#111827" />
        <x-ui.avatar name="Mo" />
        <x-ui.avatar name="Jesse van Dijk" size="lg" color="#dc2626" />
    </div>

    <h2 class="{{ $section }}">Bottom sheet en toast</h2>
    <div class="flex flex-wrap gap-3">
        <x-ui.button variant="secondary" x-on:click="$dispatch('open-sheet', 'demo')">Open bottom sheet</x-ui.button>
        <x-ui.button variant="secondary" x-on:click="$dispatch('toast', 'Afspraak opgeslagen')">Toon toast</x-ui.button>
    </div>

    <h2 class="{{ $section }}">Lege staat</h2>
    <x-ui.card :padding="false">
        <x-ui.empty-state icon="scissors" title="Nog geen diensten" text="Voeg je eerste dienst toe, dan kunnen klanten online boeken.">
            <x-ui.button>Voeg je eerste dienst toe</x-ui.button>
        </x-ui.empty-state>
    </x-ui.card>

    <x-ui.bottom-sheet name="demo" title="Daan de Vries">
        <p class="text-sm text-muted">Knippen · vandaag 14:30 · bij Sam</p>
        <div class="mt-4 space-y-3">
            <x-ui.button full icon="check" x-on:click="$dispatch('close-sheet'); $dispatch('toast', 'Voltooid')">Voltooien</x-ui.button>
            <x-ui.button full variant="secondary" icon="phone">Bellen</x-ui.button>
            <x-ui.button full variant="ghost">Annuleren</x-ui.button>
        </div>
    </x-ui.bottom-sheet>

    <x-ui.sticky-footer :nav="$layout === 'app'">
        <x-ui.button full x-on:click="$dispatch('toast', 'Dit is de primaire knop')">Primaire knop (sticky footer)</x-ui.button>
    </x-ui.sticky-footer>
</div>
