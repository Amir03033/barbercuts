{{--
    De openbare demolink van een pitcher: kopiëren, delen (deelmenu van de telefoon of WhatsApp) en hoe vaak hij gestart is.
    compact: kleinere variant voor de demopagina.
--}}
@props(['url', 'starts' => 0, 'compact' => false])

@php
    $label = preg_replace('#^https?://#', '', $url);
    $message = "Hoi! Benieuwd hoe BarberCuts werkt? Probeer het zelf in een minuut, zonder account. Je krijgt een eigen voorbeeldzaak met een volle agenda: {$url}";
@endphp

<section {{ $attributes->class(['rounded-[1.75rem] bg-card shadow-soft ring-1 ring-border/70', 'p-4' => $compact, 'p-5 md:p-6' => ! $compact]) }} data-reveal
    x-data="{
        share() {
            if (navigator.share) {
                navigator.share({ title: 'Probeer BarberCuts', text: @js($message) }).catch(() => {});
            } else {
                window.open('https://wa.me/?text=' + encodeURIComponent(@js($message)), '_blank', 'noopener');
            }
        },
    }">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 @class(['font-bold tracking-tight', 'text-base' => $compact, 'text-lg' => ! $compact])>Jouw demolink</h2>
            <p class="mt-1 text-sm text-muted">Stuur deze link naar kappers die je online vindt. Zij starten zonder account een eigen demo, en meldt iemand daarna aan, dan telt hij voor jou.</p>
        </div>
        <span class="shrink-0 rounded-pill bg-surface px-3 py-1 text-xs font-semibold text-muted" title="Zo vaak is een demo gestart via jouw link">{{ $starts }}× gestart</span>
    </div>

    <x-ui.copy-link :url="$url" :label="$label" class="mt-4" />

    <div class="mt-3 grid grid-cols-2 gap-2">
        <x-ui.button variant="secondary" size="sm" icon="share" x-on:click="share()">Delen</x-ui.button>
        <x-ui.button variant="secondary" size="sm" icon="message-circle" target="_blank" rel="noopener"
            :href="'https://wa.me/?text='.rawurlencode($message)">WhatsApp</x-ui.button>
    </div>

    @unless ($compact)
        <p class="mt-3 text-xs text-muted">Tip: open de link zelf één keer, dan weet je wat de kapper ziet. Kant-en-klare berichten staan bij <a href="{{ route('pitcher.messages') }}" wire:navigate class="font-medium text-link">Berichten</a>.</p>
    @endunless
</section>
