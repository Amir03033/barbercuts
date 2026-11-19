<div class="mx-auto max-w-sm px-4 py-8">
    <h1 class="text-[1.75rem] leading-9 font-bold tracking-tight text-text md:text-3xl">Nieuw wachtwoord</h1>
    <p class="mt-1 text-base text-muted">Kies een nieuw wachtwoord voor je account.</p>

    <form wire:submit="resetPassword" class="mt-6 space-y-4" novalidate>
        <x-ui.input label="E-mailadres" type="email" wire:model="email" autocomplete="email" inputmode="email" required />
        <x-ui.password-input label="Nieuw wachtwoord" wire:model="password" autocomplete="new-password" hint="Minimaal 8 tekens." meter required autofocus />

        <x-ui.button type="submit" loading="resetPassword" full>Wachtwoord opslaan</x-ui.button>
    </form>
</div>
