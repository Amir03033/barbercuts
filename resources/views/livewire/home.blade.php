{{-- Voorlopige landingspagina. De volledige versie (§9.4) komt in fase 4. --}}
<div class="mx-auto max-w-5xl px-4 py-12 md:py-24">
    <div class="max-w-xl">
        <h1 class="text-display font-bold tracking-tight text-text md:text-5xl md:leading-[1.1]">
            Afspraken, agenda en omzet voor je kapperszaak — op je telefoon
        </h1>
        <p class="mt-4 text-base text-muted">
            Klanten boeken zelf via je eigen link. Jij ziet je dag, je klanten en je omzet in één rustige app.
        </p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <x-ui.button :href="route('register')" wire:navigate>Start gratis</x-ui.button>
            <x-ui.button :href="route('login')" variant="secondary" wire:navigate>Inloggen</x-ui.button>
        </div>
        <p class="mt-4 text-xs text-muted">14 dagen gratis, daarna € 10 per maand.</p>
    </div>
</div>
