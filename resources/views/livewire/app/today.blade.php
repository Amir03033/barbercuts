<div>
    <x-ui.page-header :title="$shop->name" :subtitle="ucfirst(now($shop->timezone)->locale('nl')->isoFormat('dddd D MMMM'))" />

    <div class="space-y-6 px-4 py-4">
        @unless ($emailVerified)
            <x-ui.alert type="warning" title="Bevestig je e-mailadres">
                <p>We hebben je een mail gestuurd met een link.</p>
                <x-ui.button :href="route('verification.notice')" variant="ghost" size="sm" class="-ml-3">Link opnieuw sturen</x-ui.button>
            </x-ui.alert>
        @endunless

        <div class="grid grid-cols-2 gap-3">
            <x-ui.stat :label="$isOwner ? 'Omzet vandaag' : 'Mijn omzet vandaag'" :value="money($revenue)" />
            <x-ui.stat label="Afspraken" :value="$appointments->count()" />
        </div>

        @if ($next)
            <x-ui.section title="Eerstvolgende">
                <x-app.appointment-card :appointment="$next" :timezone="$shop->timezone" :show-staff="$isOwner" highlight wire:key="next-{{ $next->id }}" />
            </x-ui.section>
        @endif

        <x-ui.section title="Vandaag">
            @if ($appointments->isEmpty())
                <x-ui.card :padding="false" flat>
                    <x-ui.empty-state icon="calendar" title="Nog geen afspraken vandaag" text="Zodra klanten boeken via je link, zie je hier je dag.">
                        <x-ui.button :href="route('app.appointments.create')" variant="secondary" icon="plus" wire:navigate>Nieuwe afspraak</x-ui.button>
                    </x-ui.empty-state>
                </x-ui.card>
            @else
                <div class="space-y-2">
                    @foreach ($appointments as $appointment)
                        <x-app.appointment-card :appointment="$appointment" :timezone="$shop->timezone" :show-staff="$isOwner" wire:key="appointment-{{ $appointment->id }}" />
                    @endforeach
                </div>
            @endif
        </x-ui.section>

        @if ($checklist !== [])
            <x-ui.section title="Maak je zaak af">
                <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                    @foreach ($checklist as $item)
                        <x-ui.list-item :href="$item['done'] ? null : $item['href']" :title="$item['label']" wire:key="check-{{ $loop->index }}">
                            <x-slot:leading>
                                <x-ui.icon :name="$item['done'] ? 'circle-check' : 'circle'" @class(['text-completed' => $item['done'], 'text-border' => ! $item['done']]) />
                            </x-slot:leading>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.card>
            </x-ui.section>
        @endif

        <x-ui.section title="Je boekingslink" text="Deel deze link met je klanten.">
            <x-ui.copy-link :url="$shop->bookingUrl()" :label="$shop->bookingUrlLabel()" :action="$isOwner ? 'markLinkShared' : null" />
        </x-ui.section>

        <x-app.install-tip />
    </div>

    <x-app.appointment-sheet :appointment="$selected" :can-update="$canUpdateSelected" :mode="$sheetMode" :timezone="$shop->timezone"
        :custom-tip="$customTip" :tip="$tip" />
</div>
