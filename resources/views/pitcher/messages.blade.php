<x-layouts::platform title="Berichten">
    <div class="mx-auto max-w-3xl px-4 py-8 md:py-12">
        <p class="rise-in text-sm font-semibold text-link">Pitcher</p>
        <h1 class="rise-in mt-1 text-[1.75rem] leading-9 font-bold tracking-tight md:text-4xl" style="--rise-delay: 60ms">Berichten om te kopiëren</h1>
        <p class="rise-in mt-2 text-base text-muted" style="--rise-delay: 120ms">Kant-en-klaar voor WhatsApp en Instagram. Je naam en jouw aanmeldlink staan er al in. Vul alleen wat tussen [haakjes] staat zelf in.</p>

        <x-pitcher.tabs active="messages" />

        <div class="stagger mt-6 space-y-4">
            @foreach ($messages as $message)
                <article x-data="{ copied: false, copy() { navigator.clipboard?.writeText(@js($message['text'])); this.copied = true; $dispatch('toast', 'Bericht gekopieerd'); setTimeout(() => this.copied = false, 2200); } }"
                    class="rounded-[1.5rem] bg-card p-5 shadow-soft ring-1 ring-border/70">
                    <div class="flex items-start gap-3">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-surface text-sm font-bold text-muted">{{ $loop->iteration }}</span>
                        <div class="min-w-0">
                            <h2 class="text-base font-bold tracking-tight">{{ $message['title'] }}</h2>
                            <p class="text-sm text-muted">{{ $message['when'] }}</p>
                        </div>
                    </div>
                    <p class="mt-3 rounded-2xl bg-surface p-4 text-[0.9375rem] leading-6 whitespace-pre-line">{{ $message['text'] }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-ui.button variant="secondary" size="sm" x-on:click="copy()">
                            <span x-show="!copied">Kopiëren</span><span x-show="copied" x-cloak>Gekopieerd ✓</span>
                        </x-ui.button>
                        <x-ui.button variant="ghost" size="sm" :href="'https://wa.me/?text='.rawurlencode($message['text'])" target="_blank" rel="noopener">Open in WhatsApp</x-ui.button>
                    </div>
                </article>
            @endforeach
        </div>

        <p class="mt-6 rounded-2xl bg-gold-soft p-4 text-sm text-gold-ink"><strong>Houd het persoonlijk.</strong> Pas het bericht aan zodat het klinkt als jij, en noem iets wat je in de zaak zag. Stuur nooit hetzelfde bericht naar veel kappers tegelijk.</p>
    </div>
</x-layouts::platform>
