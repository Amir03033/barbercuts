<div>
    <x-ui.page-header title="Kappers" :back="route('app.more')" />

    <div class="px-4 py-4">
        <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
            @foreach ($staffMembers as $staff)
                @php
                    $canOpen = $isOwner || $staff->user_id === $userId;
                    $status = match (true) {
                        ! $staff->is_active => 'Niet actief',
                        $staff->user_id !== null => 'Heeft een account',
                        $staff->invited_email !== null => 'Uitgenodigd',
                        default => 'Zonder account',
                    };
                @endphp
                <x-ui.list-item :href="$canOpen ? route('app.staff.edit', $staff) : null" :title="$staff->name" :subtitle="$status" wire:key="staff-{{ $staff->id }}">
                    <x-slot:leading><x-ui.avatar :name="$staff->name" :color="$staff->color" /></x-slot:leading>
                </x-ui.list-item>
            @endforeach
        </x-ui.card>
    </div>

    @if ($isOwner)
        <x-ui.sticky-footer nav>
            <x-ui.button full icon="plus" x-on:click="$dispatch('open-sheet', 'add-staff')">Kapper toevoegen</x-ui.button>
        </x-ui.sticky-footer>

        <x-ui.bottom-sheet name="add-staff" title="Kapper toevoegen">
            <form wire:submit="add" class="space-y-4" novalidate>
                <x-ui.input label="Naam" wire:model="name" autocomplete="off" required />
                <x-ui.input label="E-mailadres" type="email" wire:model="email" inputmode="email" autocomplete="off" optional
                    hint="Met een e-mailadres krijgt de kapper een uitnodiging om zelf in te loggen." />
                <x-ui.button type="submit" loading="add" full>Toevoegen</x-ui.button>
            </form>
        </x-ui.bottom-sheet>
    @endif
</div>
