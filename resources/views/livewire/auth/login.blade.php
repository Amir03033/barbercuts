<div class="mx-auto max-w-sm px-4 py-8">
    <h1 class="text-lg font-bold text-text">Inloggen</h1>
    <p class="mt-1 text-sm text-muted">Welkom terug bij BarberCuts.</p>

    <form wire:submit="login" class="mt-6 space-y-4" novalidate>
        <x-ui.input label="E-mailadres" type="email" wire:model="email" autocomplete="email" inputmode="email" required autofocus />
        <x-ui.input label="Wachtwoord" type="password" wire:model="password" autocomplete="current-password" required />

        <x-ui.button type="submit" loading="login" full>Inloggen</x-ui.button>
    </form>

    <div class="mt-4 flex flex-col items-center gap-1">
        <x-ui.button :href="route('password.request')" variant="ghost" size="sm" wire:navigate>Wachtwoord vergeten?</x-ui.button>
        <x-ui.button :href="route('register')" variant="ghost" size="sm" wire:navigate>Nog geen account? Account maken</x-ui.button>
    </div>
</div>
