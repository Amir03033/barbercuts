<div>
    <x-ui.page-header :title="$shop->name" :back="route('shop.show', $shop->slug)" />

    <div class="space-y-5 px-4 py-4">
        <h1 class="text-lg font-bold">Wachtlijst</h1>

        <x-ui.card flat class="space-y-1 text-sm">
            <p class="text-lg font-bold">{{ ucfirst(nl_date($day, 'dddd D MMMM')) }}</p>
            <p class="font-semibold">{{ $entry->service->name }}</p>
            <p class="text-muted">{{ $entry->staffMember ? 'Bij '.$entry->staffMember->name : 'Geen voorkeur voor een kapper' }} · {{ $entry->customer->name }}</p>
        </x-ui.card>

        @if ($open && $times !== [])
            <div wire:poll.30s>
                <p class="text-base font-semibold">Er is een plek vrij</p>
                <p class="mt-1 mb-3 text-sm text-muted">Je hebt voorrang tot {{ $entry->offer_expires_at->setTimezone($shop->timezone)->format('H:i') }}. Kies een tijd.</p>
                <x-ui.time-grid :times="$times" wire:model.live="time" :selected="$time" />
                @error('time')
                    <x-ui.alert type="danger" class="mt-4">{{ $message }}</x-ui.alert>
                @enderror
            </div>

            <x-ui.sticky-footer>
                <x-ui.button full wire:click="book" loading="book" :disabled="$time === ''">{{ $time === '' ? 'Kies een tijd' : 'Boek '.$time }}</x-ui.button>
            </x-ui.sticky-footer>
        @elseif ($entry->status === \App\Enums\WaitlistStatus::Waiting || $open)
            <x-ui.alert>
                <p class="font-semibold">Je staat op de wachtlijst.</p>
                <p>Komt er op deze dag een plek vrij, dan krijg je een bericht en heb je {{ \App\Models\WaitlistEntry::OFFER_MINUTES }} minuten voorrang. Bewaar deze pagina.</p>
            </x-ui.alert>
            <x-ui.button variant="ghost" full wire:click="leave" wire:confirm="Wil je van de wachtlijst af?">Haal me van de wachtlijst</x-ui.button>
        @elseif ($entry->status === \App\Enums\WaitlistStatus::Booked)
            <x-ui.alert><p class="font-semibold">Je hebt een afspraak geboekt.</p><p>De bevestiging heb je ontvangen.</p></x-ui.alert>
        @else
            <x-ui.alert>
                <p class="font-semibold">
                    {{ match ($entry->status) {
                        \App\Enums\WaitlistStatus::Missed => 'De vrijgekomen plek is doorgegeven aan de volgende.',
                        \App\Enums\WaitlistStatus::Cancelled => 'Je staat niet meer op de wachtlijst.',
                        default => 'Deze dag is voorbij.',
                    } }}
                </p>
                <p>Kijk op de boekpagina of er een andere tijd vrij is.</p>
            </x-ui.alert>
            <x-ui.button :href="route('shop.book', $shop->slug)" full>Afspraak maken</x-ui.button>
        @endif
    </div>
</div>
