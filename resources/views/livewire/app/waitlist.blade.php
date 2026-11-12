<div>
    <x-ui.page-header title="Wachtlijst" :back="route('app.more')" />

    <div class="space-y-5 px-4 py-4">
        @if ($isOwner)
            <x-ui.card flat>
                <x-ui.toggle label="Wachtlijst bij volle dagen" hint="Klanten zetten zich op de wachtlijst. Komt er een plek vrij, dan krijgt de eerste automatisch een bericht en {{ \App\Models\WaitlistEntry::OFFER_MINUTES }} minuten voorrang."
                    :checked="$enabled" x-on:change="$wire.setEnabled($event.target.checked)" />
            </x-ui.card>
        @endif

        @forelse ($entries as $date => $group)
            <x-ui.section :title="ucfirst(nl_date(\Carbon\CarbonImmutable::parse($date), 'dddd D MMMM'))" wire:key="day-{{ $date }}">
                <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                    @foreach ($group as $entry)
                        <x-ui.list-item :href="route('app.customers.show', $entry->customer_id)" :title="$entry->customer->name" wire:key="entry-{{ $entry->id }}"
                            :subtitle="$entry->service->name.' · '.($entry->staffMember?->name ?? 'Geen voorkeur')">
                            <x-slot:trailing>
                                <x-ui.badge :status="match ($entry->status) { \App\Enums\WaitlistStatus::Booked => 'completed', \App\Enums\WaitlistStatus::Offered => 'gold', \App\Enums\WaitlistStatus::Waiting => 'scheduled', default => 'neutral' }">{{ $entry->status->label() }}</x-ui.badge>
                            </x-slot:trailing>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.card>
            </x-ui.section>
        @empty
            <x-ui.empty-state icon="clock" title="Niemand op de wachtlijst"
                text="Zit een dag vol, dan kunnen klanten zich op de boekpagina op de wachtlijst zetten. Je ziet ze hier." />
        @endforelse
    </div>
</div>
