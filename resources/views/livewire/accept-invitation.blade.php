<div class="mx-auto max-w-sm px-4 py-8">
    @if (! $staff || ! $shop)
        <x-ui.empty-state icon="alert" title="Deze uitnodiging is niet meer geldig" class="!px-0"
            text="De link is al gebruikt of verlopen. Vraag de eigenaar van de zaak om een nieuwe uitnodiging." />
    @elseif ($wrongAccount)
        <x-ui.empty-state icon="alert" title="Je bent ingelogd met een ander account" class="!px-0"
            text="Deze uitnodiging is voor {{ $email }}. Log uit en open de link uit de mail opnieuw." />
    @elseif ($accountExists)
        <x-ui.empty-state icon="user" title="Log eerst in" class="!px-0"
            text="Er bestaat al een account met {{ $email }}. Log in en open daarna de link uit de mail opnieuw.">
            <x-ui.button :href="route('login')">Inloggen</x-ui.button>
        </x-ui.empty-state>
    @else
        <h1 class="text-lg font-bold">Welkom bij {{ $shop->name }}</h1>
        <p class="mt-1 text-sm text-muted">Je bent uitgenodigd als kapper. Je krijgt je eigen agenda en ziet je eigen omzet.</p>

        <form wire:submit="accept" class="mt-6 space-y-4" novalidate>
            @if (! $loggedIn)
                <x-ui.input label="E-mailadres" type="email" name="email-readonly" :value="$email" disabled />
                <x-ui.input label="Je naam" wire:model="name" autocomplete="name" required />
                <x-ui.input label="Kies een wachtwoord" type="password" wire:model="password" autocomplete="new-password" hint="Minimaal 8 tekens." required />
            @endif
            <x-ui.button type="submit" loading="accept" full>Uitnodiging accepteren</x-ui.button>
        </form>
    @endif
</div>
