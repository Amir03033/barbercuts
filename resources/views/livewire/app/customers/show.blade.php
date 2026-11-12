<div>
    <x-ui.page-header :title="$customer->name" :back="route('app.customers')">
        <x-ui.button variant="ghost" size="sm" x-on:click="$dispatch('open-sheet', 'customer')">Wijzig</x-ui.button>
    </x-ui.page-header>

    <div class="space-y-6 px-4 py-4">
        <div class="flex items-center gap-3">
            <x-ui.avatar :name="$customer->name" size="lg" />
            <div class="min-w-0">
                <p class="truncate text-lg font-bold">{{ $customer->name }}</p>
                <p class="truncate text-sm text-muted">{{ $customer->phone }}@if ($customer->email) · {{ $customer->email }}@endif</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <x-ui.button variant="secondary" icon="phone" :href="'tel:'.$customer->phone">Bellen</x-ui.button>
            <x-ui.button variant="secondary" icon="message-circle" :href="'https://wa.me/'.ltrim($customer->phone, '+')" target="_blank" rel="noopener">WhatsApp</x-ui.button>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <x-ui.stat :label="$isOwner ? 'Totaal besteed' : 'Bij mij besteed'" :value="money($spend)" />
            <x-ui.stat label="No-shows" :value="$customer->no_show_count" />
        </div>

        <x-ui.section title="Notities">
            <form wire:submit="saveNotes" class="space-y-3" novalidate>
                <x-ui.textarea wire:model="notes" aria-label="Notities" placeholder="Bijvoorbeeld: fade 1,5 aan de zijkant" rows="3" />
                <x-ui.button type="submit" variant="secondary" loading="saveNotes" full>Notitie opslaan</x-ui.button>
            </form>
        </x-ui.section>

        @if ($loyaltyCard)
            <x-ui.card flat class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-base font-medium lg:text-sm">{{ $loyaltyCard->name }}</p>
                    <p class="text-xs text-muted">{{ $customer->loyalty_stamps }} van {{ $loyaltyCard->stamps_required }} stempels{{ $customer->loyalty_stamps >= $loyaltyCard->stamps_required ? ' · vol' : '' }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <x-ui.button variant="secondary" size="sm" wire:click="adjustStamps(-1)" aria-label="Stempel eraf" :disabled="$customer->loyalty_stamps === 0">−</x-ui.button>
                    <x-ui.button variant="secondary" size="sm" wire:click="adjustStamps(1)" aria-label="Stempel erbij">+</x-ui.button>
                </div>
            </x-ui.card>
        @endif

        <x-ui.card flat>
            <x-ui.toggle label="Berichten van de zaak" hint="Alleen aanzetten als de klant dat goed vindt. Nodig voor “klanten terughalen”."
                :checked="$customer->marketing_opt_in" x-on:change="$wire.setMarketing($event.target.checked)" />
        </x-ui.card>

        <x-ui.section title="Bezoeken">
            @if ($appointments->isEmpty())
                <p class="text-sm text-muted">Nog geen afspraken.</p>
            @else
                <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                    @foreach ($appointments as $appointment)
                        <x-ui.list-item wire:key="visit-{{ $appointment->id }}" :chevron="false" :title="$appointment->service_name"
                            :subtitle="nl_date($appointment->localStart(), 'dd D MMM YYYY').' '.$appointment->localStart()->format('H:i').' · '.$appointment->staffMember->name">
                            <x-slot:trailing><x-ui.badge :status="$appointment->status->value" /></x-slot:trailing>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.card>
            @endif
        </x-ui.section>
    </div>

    <x-ui.sticky-footer nav>
        <x-ui.button full icon="plus" :href="route('app.appointments.create', ['klant' => $customer->id])" wire:navigate>Nieuwe afspraak</x-ui.button>
    </x-ui.sticky-footer>

    <x-ui.bottom-sheet name="customer" title="Klant wijzigen">
        <form wire:submit="saveDetails" class="space-y-4" novalidate>
            <x-ui.input label="Naam" wire:model="name" autocomplete="off" />
            <x-ui.phone-input wire:model="phone" autocomplete="off" />
            <x-ui.input label="E-mailadres" type="email" wire:model="email" inputmode="email" autocomplete="off" optional />
            <x-ui.button type="submit" loading="saveDetails" full>Opslaan</x-ui.button>
        </form>
    </x-ui.bottom-sheet>
</div>
