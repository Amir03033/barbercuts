<div>
    <x-ui.page-header :title="$staff->name" :back="$canManage ? route('app.staff') : route('app.more')" />

    <div class="space-y-8 px-4 py-4">
        @if ($canManage)
            <x-ui.section title="Profiel">
                <form wire:submit="saveProfile" class="space-y-4" novalidate>
                    <x-ui.input label="Naam" wire:model="name" autocomplete="off" required />
                    <x-ui.field label="Kleur in de agenda" name="color">
                        <x-ui.color-swatches :colors="$colors" wire:model="color" :value="$color" label="Kleur in de agenda" />
                    </x-ui.field>
                    <x-ui.textarea label="Over" wire:model="bio" optional hint="Kort stukje voor op je boekingspagina." />
                    <x-ui.toggle label="Actief" hint="Niet actief: klanten kunnen niet meer bij deze kapper boeken." wire:model="isActive" :checked="$isActive" />
                    <x-ui.button type="submit" loading="saveProfile" full>Profiel opslaan</x-ui.button>
                </form>
            </x-ui.section>

            <x-ui.section title="Inloggen" :text="$staff->user_id ? 'Deze kapper heeft een account en ziet de eigen agenda en omzet.' : 'Nodig de kapper uit om zelf in te loggen.'">
                @unless ($staff->user_id)
                    <form wire:submit="invite" class="space-y-3" novalidate>
                        <x-ui.input label="E-mailadres" type="email" wire:model="inviteEmail" inputmode="email" autocomplete="off"
                            :hint="$staff->invited_at ? 'Uitgenodigd op '.nl_date($staff->invited_at->setTimezone($timezone), 'D MMM YYYY').'. De link is 7 dagen geldig.' : null" />
                        <x-ui.button type="submit" variant="secondary" loading="invite" icon="mail" full>
                            {{ $staff->invited_at ? 'Uitnodiging opnieuw sturen' : 'Uitnodiging sturen' }}
                        </x-ui.button>
                    </form>
                @endunless
            </x-ui.section>
        @endif

        <x-ui.section title="Werktijden" text="Meerdere blokken op een dag = pauze ertussen. Buiten de openingstijden van de zaak kan niet geboekt worden.">
            <form wire:submit="saveHours" class="space-y-3" novalidate>
                <x-ui.week-hours :week="$week" :errors="$weekErrors" />
                <x-ui.button type="submit" loading="saveHours" full>Werktijden opslaan</x-ui.button>
            </form>
        </x-ui.section>

        <x-ui.section title="Vrij en afwijkende uren" text="Vakantie, ziek, een vrije dag of een dag met andere uren.">
            <x-ui.time-off-list :items="$timeOff" :timezone="$timezone" />
            <x-ui.button variant="secondary" icon="plus" full x-on:click="$dispatch('open-sheet', 'time-off')">Toevoegen</x-ui.button>
        </x-ui.section>

        @if ($canManage)
            <x-ui.button variant="ghost" icon="trash" full wire:click="delete" wire:confirm="Weet je zeker dat je {{ $staff->name }} wilt verwijderen?">Kapper verwijderen</x-ui.button>
        @endif
    </div>

    <x-ui.bottom-sheet name="time-off" title="Vrij of afwijkende uren">
        <form wire:submit="addTimeOff" class="space-y-4" novalidate>
            <x-ui.segmented label="Soort" wire:model.live="offType" :value="$offType" name="offType"
                :options="['closed' => 'Vrij', 'custom_hours' => 'Andere uren']" />

            @if ($offType === 'custom_hours')
                <x-ui.input label="Datum" type="date" wire:model="offFrom" />
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input label="Van" type="time" wire:model="offStart" />
                    <x-ui.input label="Tot" type="time" wire:model="offEnd" />
                </div>
            @else
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input label="Van" type="date" wire:model="offFrom" />
                    <x-ui.input label="Tot en met" type="date" wire:model="offTo" optional />
                </div>
            @endif

            <x-ui.input label="Reden" wire:model="offReason" optional placeholder="Bijvoorbeeld: vakantie" />
            <x-ui.button type="submit" loading="addTimeOff" full>Toevoegen</x-ui.button>
        </form>
    </x-ui.bottom-sheet>
</div>
