<div class="mx-auto max-w-sm px-4 py-8 lg:py-0">
    <h1 class="rise-in text-[1.75rem] leading-9 font-bold tracking-tight text-text md:text-3xl">Maak een account</h1>
    <p class="rise-in mt-1 text-base text-muted" style="--rise-delay: 80ms">Al je afspraken bij alle kappers op één plek. Gratis en in een minuut geregeld.</p>

    <ul class="rise-in mt-5 space-y-2 text-sm text-muted" style="--rise-delay: 120ms">
        @foreach (['Afspraken verplaatsen of annuleren met één tik', 'Herinneringen voordat je afspraak begint', 'Sneller boeken: je gegevens staan al klaar'] as $line)
            <li class="flex items-center gap-2.5"><x-ui.icon name="check" size="16" class="shrink-0 text-completed" />{{ $line }}</li>
        @endforeach
    </ul>

    <form wire:submit="register" class="rise-in mt-7 space-y-4" style="--rise-delay: 180ms" novalidate>
        <x-ui.input label="Naam" wire:model="name" autocomplete="name" required autofocus />
        <x-ui.input label="E-mailadres" type="email" wire:model="email" autocomplete="email" inputmode="email" required />
        <x-ui.password-input label="Wachtwoord" wire:model="password" autocomplete="new-password" hint="Minimaal 8 tekens." meter required />

        <x-ui.button type="submit" loading="register" full>Account maken</x-ui.button>
    </form>

    <p class="mt-4 text-center text-xs text-muted">Door door te gaan ga je akkoord met de <a href="{{ route('terms') }}" class="font-medium underline" target="_blank" rel="noopener">voorwaarden</a> en de <a href="{{ route('privacy') }}" class="font-medium underline" target="_blank" rel="noopener">privacyverklaring</a>.</p>

    <div class="rise-in mt-6 space-y-2 border-t border-border pt-5 text-center text-sm" style="--rise-delay: 240ms">
        <p class="text-muted">Al een account? <a href="{{ route('login') }}" wire:navigate class="inline-flex min-h-9 items-center font-medium text-link">Inloggen</a></p>
        <p class="text-muted">Kapper of eigenaar van een zaak? <a href="{{ route('onboarding') }}" wire:navigate class="inline-flex min-h-9 items-center font-medium text-link">Meld je zaak aan</a></p>
    </div>
</div>
