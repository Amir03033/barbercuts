{{--
    Melding van een "live" online boeking in een demozaak, zoals een pushmelding op een iPhone: schuift bovenin binnen,
    verdwijnt na zeven seconden, tik erop om de afspraak in de agenda te zien.
--}}
<div @if ($active) wire:poll.15s="tick" @endif
    x-data="{ booking: null, timer: null }"
    x-on:demo-booking.window="booking = $event.detail; clearTimeout(timer); timer = setTimeout(() => booking = null, 7000)">
    <template x-if="booking">
        <a :href="booking.url" wire:navigate role="status" aria-live="polite"
            x-transition:enter="transition duration-500 ease-out-soft" x-transition:enter-start="-translate-y-6 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
            class="fixed inset-x-3 z-[70] mx-auto flex max-w-md items-center gap-3 rounded-[1.4rem] bg-card/90 p-3.5 pr-4 shadow-pop ring-1 ring-border/70 backdrop-blur-xl"
            style="top: calc(env(safe-area-inset-top) + 0.75rem)">
            <span class="relative flex size-11 shrink-0 items-center justify-center rounded-xl bg-text text-white">
                <x-ui.icon name="calendar" size="22" />
                <span class="absolute -top-1 -right-1 size-3 animate-pulse-dot rounded-full bg-completed ring-2 ring-card"></span>
            </span>
            <span class="min-w-0 flex-1">
                <span class="flex items-center justify-between gap-2 text-xs text-muted"><span class="font-semibold text-text">Nieuwe online boeking</span><span>nu</span></span>
                <span class="mt-0.5 block truncate text-sm font-semibold text-text" x-text="booking.customer + ' · ' + booking.service"></span>
                <span class="block truncate text-sm text-muted" x-text="booking.when + ' bij ' + booking.staff"></span>
            </span>
        </a>
    </template>
</div>
