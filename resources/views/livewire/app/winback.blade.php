<div>
    <x-ui.page-header title="Klanten terughalen" :back="route('app.more')" />

    <div class="space-y-6 px-4 py-4">
        <x-ui.section title="Afgelopen 90 dagen">
            <div class="grid grid-cols-3 gap-3">
                <x-ui.stat label="Verstuurd" :value="$results['sent']" />
                <x-ui.stat label="Teruggekomen" :value="$results['returned']" />
                <x-ui.stat label="Omzet" :value="money($results['revenue_cents'])" />
            </div>
            <p class="text-xs text-muted">Teruggekomen = een afspraak gemaakt binnen {{ \App\Services\WinbackService::ATTRIBUTION_DAYS }} dagen na het bericht.</p>
        </x-ui.section>

        <form wire:submit="save" class="space-y-5" novalidate>
            <x-ui.card flat class="space-y-4">
                <x-ui.toggle label="Stuur automatisch een bericht" hint="Naar klanten die een tijd niet zijn geweest en geen afspraak hebben staan. Hooguit één keer per {{ \App\Services\WinbackService::COOLDOWN_DAYS }} dagen per klant."
                    wire:model.live="enabled" :checked="$enabled" />

                <div>
                    <p class="mb-2 text-sm font-medium">Na hoeveel weken zonder bezoek?</p>
                    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Aantal weken">
                        @foreach (\App\Services\WinbackService::WEEK_OPTIONS as $option)
                            <x-ui.chip name="weeks" :value="$option" wire:model.live="weeks" :checked="(int) $weeks === $option">{{ $option }} weken</x-ui.chip>
                        @endforeach
                    </div>
                </div>

                <x-ui.textarea label="Tekst" wire:model="message" optional rows="3" maxlength="400" :placeholder="$defaultText"
                    hint="Leeg = de standaardtekst. De aanhef, de boeklink en een afmeldlink komen er vanzelf bij." />
            </x-ui.card>

            <x-ui.alert>
                <p><strong>{{ $eligible }}</strong> {{ $eligible === 1 ? 'klant komt' : 'klanten komen' }} nu in aanmerking.</p>
                <p class="mt-1">Alleen klanten die berichten van je zaak willen ontvangen krijgen dit bericht ({{ $optedIn }} van je klanten). Klanten geven dat aan bij het boeken; jij kunt het aanzetten op de pagina van de klant als die het goed vindt.</p>
            </x-ui.alert>

            <x-ui.sticky-footer nav>
                <x-ui.button type="submit" loading="save" full>Opslaan</x-ui.button>
            </x-ui.sticky-footer>
        </form>
    </div>
</div>
