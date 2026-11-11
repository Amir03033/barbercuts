<div>
    <x-ui.page-header title="Mijn profiel" :subtitle="$user->email" :back="route('app.more')" />

    <div class="space-y-6 px-4 py-4">
        <div class="flex items-center gap-4">
            <x-ui.avatar :name="$user->name" size="lg" />
            <div class="min-w-0">
                <p class="truncate text-base font-semibold">{{ $user->name }}</p>
                <p class="truncate text-sm text-muted">{{ $user->isOwnerOf($shop) ? 'Eigenaar' : 'Kapper' }} bij {{ $shop->name }}</p>
            </div>
        </div>

        <x-ui.section title="Meldingen">
            <x-push-toggle title="Pushmeldingen" text="Krijg een melding bij een nieuwe online boeking, annulering of verplaatsing." />
        </x-ui.section>

        <x-ui.section title="Gegevens">
            <x-ui.card>
                <form wire:submit="saveProfile" class="space-y-4" novalidate>
                    <x-ui.input label="Naam" wire:model="name" autocomplete="name" required />
                    <x-ui.phone-input wire:model="phone" optional />
                    <x-ui.input label="E-mailadres" :value="$user->email" disabled hint="Je e-mailadres is je login en kun je hier niet wijzigen." />
                    <x-ui.button type="submit" loading="saveProfile">Opslaan</x-ui.button>
                </form>
            </x-ui.card>
        </x-ui.section>

        <x-ui.section title="Wachtwoord wijzigen">
            <x-ui.card>
                <form wire:submit="savePassword" class="space-y-4" novalidate>
                    <x-ui.input label="Huidig wachtwoord" type="password" wire:model="currentPassword" autocomplete="current-password" required />
                    <x-ui.input label="Nieuw wachtwoord" type="password" wire:model="newPassword" autocomplete="new-password" hint="Minimaal 8 tekens." required />
                    <x-ui.button type="submit" variant="secondary" loading="savePassword">Wachtwoord wijzigen</x-ui.button>
                </form>
            </x-ui.card>
        </x-ui.section>
    </div>
</div>
