{{--
    Bevestig je e-mailadres. Gaat vanzelf verder: elke paar seconden (ook als de link op een ander apparaat is aangetikt),
    zodra je terugkomt in dit tabblad, en meteen bij een seintje van het tabblad waarin de link opende.
--}}
<div class="mx-auto max-w-sm px-4 py-8 lg:py-0"
    wire:poll.4s="check"
    x-data="{
        init() {
            try {
                this.channel = new BroadcastChannel('barbercuts');
                this.channel.onmessage = (event) => event.data === 'email-verified' && $wire.check();
            } catch (error) {}
        },
        destroy() { this.channel?.close(); },
    }"
    x-on:storage.window="$event.key === 'barbercuts:email-verified' && $wire.check()"
    x-on:visibilitychange.document="document.visibilityState === 'visible' && $wire.check()"
    x-on:focus.window="$wire.check()">

    <div class="relative mx-auto flex size-16 items-center justify-center">
        <span class="absolute inset-0 animate-ping rounded-full bg-gold-soft opacity-60 [animation-duration:2.4s]" aria-hidden="true"></span>
        <span class="relative flex size-16 items-center justify-center rounded-full bg-gold-soft text-gold-ink">
            <x-ui.icon name="mail" size="28" />
        </span>
    </div>

    <h1 class="rise-in mt-5 text-center text-[1.75rem] leading-9 font-bold tracking-tight text-text">Check je mail</h1>
    <p class="rise-in mt-2 text-center text-base text-muted" style="--rise-delay: 60ms">
        We hebben een link gestuurd naar<br><strong class="font-semibold break-all text-text">{{ $email }}</strong>
    </p>

    <ol class="rise-in mt-6 space-y-3 rounded-card bg-surface p-4 text-sm text-muted" style="--rise-delay: 120ms">
        <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-card text-xs font-semibold text-text shadow-soft">1</span><span class="pt-0.5">Open de mail <strong class="font-semibold text-text">"Bevestig je e-mailadres"</strong>. Niets binnen? Kijk ook bij ongewenste mail of reclame.</span></li>
        <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-card text-xs font-semibold text-text shadow-soft">2</span><span class="pt-0.5">Tik op de knop in de mail. Dat mag ook op een ander apparaat.</span></li>
        <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-card text-xs font-semibold text-text shadow-soft">3</span><span class="pt-0.5">Dit scherm gaat dan vanzelf verder.</span></li>
    </ol>

    <p class="mt-4 flex items-center justify-center gap-2 text-xs text-muted" role="status">
        <span class="size-1.5 animate-pulse-dot rounded-full bg-completed" aria-hidden="true"></span>
        Wachten op je bevestiging…
    </p>

    @if ($editingEmail)
        <form wire:submit="updateEmail" class="mt-6 space-y-3" novalidate>
            <x-ui.input label="Juiste e-mailadres" type="email" wire:model="newEmail" autocomplete="email" inputmode="email" required autofocus />
            <x-ui.button type="submit" loading="updateEmail" full>Opslaan en link sturen</x-ui.button>
            <x-ui.button variant="ghost" wire:click="cancelEditEmail" full>Annuleren</x-ui.button>
        </form>
    @else
        <div class="mt-6 space-y-2">
            <x-ui.button wire:click="resend" loading="resend" variant="secondary" full>Stuur de link opnieuw</x-ui.button>
            <x-ui.button wire:click="editEmail" variant="ghost" full>Verkeerd e-mailadres? Wijzig het</x-ui.button>
        </div>
    @endif

    <div class="mt-6 border-t border-border pt-5 text-center">
        <a href="{{ $continueUrl }}" class="inline-flex min-h-11 items-center text-sm font-medium text-link">Later bevestigen en verdergaan</a>
        <p class="mt-1 text-xs text-muted">De link in de mail blijft {{ $hours }} uur geldig.</p>
    </div>
</div>
