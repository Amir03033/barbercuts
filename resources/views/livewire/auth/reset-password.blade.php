<div class="mx-auto max-w-sm px-4 py-8">
    <h1 class="text-lg font-bold text-text">Nieuw wachtwoord</h1>
    <p class="mt-1 text-sm text-muted">Kies een nieuw wachtwoord voor je account.</p>

    <form wire:submit="resetPassword" class="mt-6 space-y-4" novalidate>
        <x-ui.input label="E-mailadres" type="email" wire:model="email" autocomplete="email" inputmode="email" required />
        <x-ui.input label="Nieuw wachtwoord" type="password" wire:model="password" autocomplete="new-password" hint="Minimaal 8 tekens." required autofocus />

        <x-ui.button type="submit" loading="resetPassword" full>Wachtwoord opslaan</x-ui.button>
    </form>
</div>
