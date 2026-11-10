{{--
    Bottom sheet met de acties voor één afspraak (§9.1). Hoort bij de trait HandlesAppointmentActions.
    appointment: de gekozen afspraak (of null) · canUpdate: mag de kijker deze afspraak wijzigen (policy).
--}}
@props(['appointment', 'canUpdate', 'mode', 'timezone', 'customTip' => false, 'tip' => '0'])

<x-ui.bottom-sheet name="appointment" :title="$appointment?->customer->name ?? 'Afspraak'">
    @if ($appointment)
        @php
            $start = $appointment->starts_at->toImmutable()->setTimezone($timezone);
            $status = $appointment->status->value;
            $started = $appointment->starts_at->isPast();
            $phone = $appointment->customer->phone;
        @endphp

        <div class="flex items-start justify-between gap-3">
            <p class="text-sm text-muted">
                {{ $appointment->service_name }} · {{ human_date($start, $timezone) }} {{ $start->format('H:i') }} · bij {{ $appointment->staffMember->name }}
                @if ($appointment->notes)
                    <br>“{{ $appointment->notes }}”
                @endif
            </p>
            <x-ui.badge :status="$status" />
        </div>

        @if ($mode === 'complete' && $canUpdate)
            {{-- Voltooien: bedrag, fooi en betaalmethode. De betaalmethode slaat meteen op. --}}
            <div class="mt-4 space-y-4">
                <x-ui.money-input label="Bedrag" wire:model="amount" />

                <x-ui.field label="Fooi" name="tip">
                    <div class="flex flex-wrap gap-2">
                        @foreach (['0' => '€ 0', '2' => '€ 2', '5' => '€ 5'] as $value => $label)
                            <x-ui.chip wire:click="setTip('{{ $value }}')" :active="! $customTip && $tip === (string) $value">{{ $label }}</x-ui.chip>
                        @endforeach
                        <x-ui.chip wire:click="setTip('custom')" :active="$customTip">Anders</x-ui.chip>
                    </div>
                    @if ($customTip)
                        <div class="mt-2">
                            <x-ui.money-input wire:model="tip" aria-label="Fooi" name="tip-custom" />
                        </div>
                    @endif
                </x-ui.field>

                <x-ui.field label="Betaald met">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['pin' => 'Pin', 'cash' => 'Contant', 'tikkie' => 'Tikkie', 'other' => 'Anders'] as $method => $label)
                            <x-ui.button wire:click="completeAppointment('{{ $method }}')" loading="completeAppointment('{{ $method }}')" class="!min-h-14">{{ $label }}</x-ui.button>
                        @endforeach
                    </div>
                </x-ui.field>

                <x-ui.button variant="ghost" size="sm" full wire:click="showSheet('actions')">Terug</x-ui.button>
            </div>
        @elseif ($mode === 'cancel' && $canUpdate)
            <div class="mt-4 space-y-3">
                <p class="text-sm">Weet je zeker dat je deze afspraak wilt annuleren? De klant krijgt hiervan (nog) geen bericht.</p>
                <x-ui.button variant="danger" full wire:click="cancelAppointment" loading="cancelAppointment">Ja, annuleren</x-ui.button>
                <x-ui.button variant="secondary" full wire:click="showSheet('actions')">Nee, laat staan</x-ui.button>
            </div>
        @else
            <div class="mt-4 space-y-3">
                @if ($canUpdate && $status === 'scheduled')
                    @if ($started)
                        <x-ui.button full icon="check" wire:click="showSheet('complete')">Voltooien</x-ui.button>
                    @endif
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.button variant="secondary" :href="route('app.appointments.reschedule', $appointment)" wire:navigate>Verplaatsen</x-ui.button>
                        <x-ui.button variant="secondary" wire:click="showSheet('cancel')">Annuleren</x-ui.button>
                    </div>
                    @if ($started)
                        <x-ui.button variant="secondary" full wire:click="markNoShow" loading="markNoShow" wire:confirm="Is de klant niet gekomen?">No-show</x-ui.button>
                    @endif
                @elseif ($status === 'completed')
                    <x-ui.card flat class="!bg-surface text-sm">
                        <p class="flex justify-between"><span>Betaald</span><span class="font-semibold tabular-nums">{{ money((int) $appointment->amount_paid_cents) }}</span></p>
                        @if ($appointment->tip_cents)
                            <p class="flex justify-between text-muted"><span>Fooi</span><span class="tabular-nums">{{ money($appointment->tip_cents) }}</span></p>
                        @endif
                        <p class="text-muted">{{ \App\Services\ShopStatsService::paymentMethodLabel($appointment->payment_method?->value) }}</p>
                    </x-ui.card>
                    @if ($canUpdate && $appointment->completed_at?->gt(now()->subDay()))
                        <x-ui.button variant="secondary" full wire:click="undoComplete" loading="undoComplete">Terugzetten naar gepland</x-ui.button>
                    @endif
                @elseif (! $canUpdate)
                    <x-ui.alert>Deze afspraak staat in de agenda van {{ $appointment->staffMember->name }}. Je kunt hem bekijken, niet wijzigen.</x-ui.alert>
                @endif

                <div class="grid grid-cols-2 gap-3">
                    <x-ui.button variant="ghost" icon="phone" :href="'tel:'.$phone">Bellen</x-ui.button>
                    <x-ui.button variant="ghost" icon="message-circle" :href="'https://wa.me/'.ltrim($phone, '+')" target="_blank" rel="noopener">WhatsApp</x-ui.button>
                </div>
                <x-ui.button variant="ghost" size="sm" full :href="route('app.customers.show', $appointment->customer_id)" wire:navigate>Klant bekijken</x-ui.button>
            </div>
        @endif
    @endif
</x-ui.bottom-sheet>
