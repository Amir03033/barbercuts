{{-- Pushmeldingen aan- of uitzetten op dit apparaat (§7.6). Alleen zichtbaar als de VAPID-sleutels zijn ingesteld en de gebruiker is ingelogd. --}}
@props(['title' => 'Meldingen op dit apparaat', 'text' => 'Krijg een melding op je telefoon, ook als de app dicht is.'])

@if (config('webpush.vapid.public_key') && auth()->check())
    <div x-data="bcPush(@js(config('webpush.vapid.public_key')), @js(route('push.subscribe')), @js(route('push.unsubscribe')))" x-cloak
        {{ $attributes->class('flex items-center gap-4 rounded-card border border-border bg-card p-4 shadow-soft') }}>
        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gold-soft text-gold-ink"><x-ui.icon name="bell" size="20" /></span>
        <div class="min-w-0 flex-1">
            <p class="text-base font-semibold lg:text-sm">{{ $title }}</p>
            <p class="text-xs text-muted" x-show="state === 'off' || state === 'checking'">{{ $text }}</p>
            <p class="text-xs text-muted" x-show="state === 'on'">Aan. Je krijgt meldingen op dit apparaat.</p>
            <p class="text-xs text-muted" x-show="state === 'denied'">Meldingen zijn geblokkeerd in je browser. Zet ze aan bij de site-instellingen.</p>
            <p class="text-xs text-muted" x-show="state === 'unsupported'">Dit apparaat of deze browser ondersteunt geen pushmeldingen. Op een iPhone: zet de app eerst op je beginscherm.</p>
        </div>
        <template x-if="state === 'off'">
            <x-ui.button size="sm" x-on:click="enable()" x-bind:disabled="busy">Aanzetten</x-ui.button>
        </template>
        <template x-if="state === 'on'">
            <x-ui.button size="sm" variant="secondary" x-on:click="disable()" x-bind:disabled="busy">Uitzetten</x-ui.button>
        </template>
    </div>
@endif
