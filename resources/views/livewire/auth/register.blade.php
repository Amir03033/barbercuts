<div class="mx-auto max-w-sm px-4 py-8">
    <h1 class="text-lg font-bold text-text">Account maken</h1>
    <p class="mt-1 text-sm text-muted">In een minuut geregeld.</p>

    <form wire:submit="register" class="mt-6 space-y-4" novalidate>
        <x-ui.input label="Naam" wire:model="name" autocomplete="name" required autofocus />
        <x-ui.input label="E-mailadres" type="email" wire:model="email" autocomplete="email" inputmode="email" required />
        <x-ui.input label="Wachtwoord" type="password" wire:model="password" autocomplete="new-password" hint="Minimaal 8 tekens." required />

        <x-ui.button type="submit" loading="register" full>Account maken</x-ui.button>
    </form>

    <div class="mt-4 flex justify-center">
        <x-ui.button :href="route('login')" variant="ghost" size="sm" wire:navigate>Al een account? Inloggen</x-ui.button>
    </div>
</div>
