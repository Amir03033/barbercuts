<div class="mx-auto max-w-sm px-4 py-8 lg:py-0">
    <h1 class="rise-in text-[1.75rem] leading-9 font-bold tracking-tight text-text md:text-3xl">Welkom terug</h1>
    <p class="rise-in mt-1 text-base text-muted" style="--rise-delay: 80ms">Log in om je agenda of je afspraken te bekijken.</p>

    <form wire:submit="login" class="rise-in mt-8 space-y-4" style="--rise-delay: 160ms" novalidate>
        <x-ui.input label="E-mailadres" type="email" wire:model="email" autocomplete="email" inputmode="email" required autofocus />
        <div>
            <x-ui.password-input label="Wachtwoord" wire:model="password" autocomplete="current-password" required />
            <div class="mt-1 flex justify-end">
                <a href="{{ route('password.request') }}" wire:navigate class="inline-flex min-h-9 items-center text-sm font-medium text-link">Wachtwoord vergeten?</a>
            </div>
        </div>

        <x-ui.button type="submit" loading="login" full>Inloggen</x-ui.button>
    </form>

    <div class="rise-in mt-8 space-y-3" style="--rise-delay: 240ms">
        <div class="flex items-center gap-3 text-xs text-muted" aria-hidden="true"><span class="h-px flex-1 bg-border"></span>Nog geen account?<span class="h-px flex-1 bg-border"></span></div>
        <div class="grid gap-2 sm:grid-cols-2">
            <x-ui.button :href="route('register')" variant="secondary" full wire:navigate>Ik ben klant</x-ui.button>
            <x-ui.button :href="route('onboarding')" variant="secondary" full wire:navigate>Ik heb een zaak</x-ui.button>
        </div>
        <p class="text-center text-xs text-muted">Een klantaccount is gratis en geeft je al je afspraken op één plek.</p>
    </div>
</div>
