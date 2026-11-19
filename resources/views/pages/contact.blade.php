@php
    $email = config('barbercuts.company.email');
    $phone = config('barbercuts.company.phone');
@endphp

<x-layouts::platform title="Contact" description="Een vraag, een idee of hulp nodig? Stuur BarberCuts een bericht. We lezen alles zelf.">
    <div class="mx-auto max-w-2xl px-4 py-10 md:py-16">
        <p class="rise-in text-sm font-semibold text-link">Contact</p>
        <h1 class="rise-in mt-1 text-[2rem] leading-10 font-bold tracking-tight md:text-5xl md:leading-[1.1]" style="--rise-delay: 80ms">We helpen je graag.</h1>
        <p class="rise-in mt-3 text-base text-muted md:text-lg" style="--rise-delay: 160ms">Een vraag, een idee of hulp nodig? We lezen alles zelf en reageren meestal binnen één werkdag.</p>

        <div class="mt-8 grid gap-3 sm:grid-cols-2" data-reveal>
            <a href="mailto:{{ $email }}" class="group flex items-center gap-4 rounded-[1.5rem] bg-card p-5 shadow-soft ring-1 ring-border/70 transition-[transform,box-shadow] duration-200 ease-out-soft hover:-translate-y-0.5 hover:shadow-pop active:scale-[0.99]">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-accent text-on-accent"><x-ui.icon name="mail" /></span>
                <span class="min-w-0"><span class="block text-base font-semibold">E-mail</span><span class="block truncate text-sm text-muted">{{ $email }}</span></span>
            </a>
            @if ($phone)
                <a href="tel:{{ $phone }}" class="group flex items-center gap-4 rounded-[1.5rem] bg-card p-5 shadow-soft ring-1 ring-border/70 transition-[transform,box-shadow] duration-200 ease-out-soft hover:-translate-y-0.5 hover:shadow-pop active:scale-[0.99]">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-completed/15 text-completed"><x-ui.icon name="phone" /></span>
                    <span class="min-w-0"><span class="block text-base font-semibold">Bellen</span><span class="block truncate text-sm text-muted">{{ $phone }}</span></span>
                </a>
            @endif
        </div>

        <div class="mt-8" data-reveal>
            <h2 class="text-xl font-bold tracking-tight">Misschien staat het antwoord er al</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <a href="{{ route('pricing') }}" wire:navigate class="flex items-center justify-between gap-3 rounded-[1.25rem] bg-surface px-5 py-4 text-base font-medium transition-colors hover:bg-border">Prijzen en veelgestelde vragen <x-ui.icon name="arrow-right" size="18" class="shrink-0 text-muted" /></a>
                <a href="{{ route('privacy') }}" class="flex items-center justify-between gap-3 rounded-[1.25rem] bg-surface px-5 py-4 text-base font-medium transition-colors hover:bg-border">Privacy en je gegevens <x-ui.icon name="arrow-right" size="18" class="shrink-0 text-muted" /></a>
            </div>
            <p class="mt-4 flex gap-3 rounded-[1.25rem] bg-gold-soft p-4 text-sm text-gold-ink"><x-ui.icon name="info" size="18" class="mt-0.5 shrink-0" /><span><strong>Ben je klant van een kapperszaak?</strong> Voor je afspraak, een verplaatsing of een vraag over je gegevens bij die zaak neem je het beste rechtstreeks contact op met de zaak zelf.</span></p>
        </div>

        <dl class="mt-10 space-y-1 border-t border-border pt-6 text-sm text-muted">
            <div class="font-medium text-text">{{ config('barbercuts.company.name') }}</div>
            @if (config('barbercuts.company.address'))<dd>{{ config('barbercuts.company.address') }}</dd>@endif
            @if (config('barbercuts.company.kvk'))<dd>KvK {{ config('barbercuts.company.kvk') }}</dd>@endif
            @if (config('barbercuts.company.vat'))<dd>Btw-nummer {{ config('barbercuts.company.vat') }}</dd>@endif
        </dl>
    </div>
</x-layouts::platform>
