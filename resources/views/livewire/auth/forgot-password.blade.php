<div class="mx-auto max-w-sm px-4 py-8">
    <h1 class="text-lg font-bold text-text">Wachtwoord vergeten</h1>

    @if ($sent)
        <x-ui.empty-state icon="mail" title="Kijk in je mailbox" class="!px-0"
            text="Als dit e-mailadres bij ons bekend is, staat er binnen een paar minuten een link in je mailbox om een nieuw wachtwoord te kiezen.">
            <x-ui.button :href="route('login')" variant="secondary" wire:navigate>Terug naar inloggen</x-ui.button>
        </x-ui.empty-state>
    @else
        <p class="mt-1 text-sm text-muted">Vul je e-mailadres in. Je krijgt een link om een nieuw wachtwoord te kiezen.</p>

        <form wire:submit="sendResetLink" class="mt-6 space-y-4" novalidate>
            <x-ui.input label="E-mailadres" type="email" wire:model="email" autocomplete="email" inputmode="email" required autofocus />
            <x-ui.button type="submit" loading="sendResetLink" full>Stuur link</x-ui.button>
        </form>

        <div class="mt-4 flex justify-center">
            <x-ui.button :href="route('login')" variant="ghost" size="sm" wire:navigate>Terug naar inloggen</x-ui.button>
        </div>
    @endif
</div>
