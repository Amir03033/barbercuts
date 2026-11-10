<div>
    <x-ui.page-header :title="$shop->name" :subtitle="ucfirst(now($shop->timezone)->locale('nl')->isoFormat('dddd D MMMM'))" />

    <div class="space-y-6 px-4 py-4">
        @unless ($emailVerified)
            <x-ui.alert type="warning" title="Bevestig je e-mailadres">
                <p>We hebben je een mail gestuurd met een link.</p>
                <x-ui.button :href="route('verification.notice')" variant="ghost" size="sm" class="-ml-3">Link opnieuw sturen</x-ui.button>
            </x-ui.alert>
        @endunless

        <x-ui.section title="Je boekingslink" text="Deel deze link met je klanten.">
            <x-ui.copy-link :url="$shop->bookingUrl()" :label="$shop->bookingUrlLabel()" :action="$isOwner ? 'markLinkShared' : null" />
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

        <x-ui.empty-state icon="calendar" title="Nog geen afspraken vandaag"
            text="Zodra klanten boeken via je link, zie je hier je dag." />
    </div>
</div>
