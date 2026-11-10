<div class="mx-auto max-w-2xl space-y-8 px-4 py-6">
    <div>
        <h1 class="text-lg font-bold">Mijn afspraken</h1>
        <p class="text-sm text-muted">{{ $user->name }} · {{ $user->email }}</p>
    </div>

    <x-ui.section title="Komende afspraken">
        @if ($upcoming->isEmpty())
            <x-ui.card :padding="false" flat>
                <x-ui.empty-state icon="calendar" title="Geen komende afspraken" text="Boek via de link van je kapper. Je afspraken verschijnen daarna hier." />
            </x-ui.card>
        @else
            <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                @foreach ($upcoming as $appointment)
                    <x-ui.list-item :href="$appointment->manageUrl()" wire:navigate.remove wire:key="upcoming-{{ $appointment->id }}"
                        :title="ucfirst($appointment->whenLabel())"
                        :subtitle="$appointment->service_name.' · '.$appointment->barbershop->name.' · '.$appointment->staffMember->name" />
                @endforeach
            </x-ui.card>
        @endif
    </x-ui.section>

    @if ($past->isNotEmpty())
        <x-ui.section title="Geschiedenis">
            <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                @foreach ($past as $appointment)
                    <x-ui.list-item wire:key="past-{{ $appointment->id }}" :chevron="false"
                        :title="$appointment->service_name.' · '.$appointment->barbershop->name"
                        :subtitle="nl_date($appointment->localStart(), 'dd D MMM YYYY').' · '.$appointment->staffMember->name">
                        <x-slot:trailing>
                            @if ($appointment->status === \App\Enums\AppointmentStatus::Scheduled)
                                <x-ui.badge status="neutral">Geweest</x-ui.badge>
                            @else
                                <x-ui.badge :status="$appointment->status->value" />
                            @endif
                            @if ($appointment->barbershop->is_public)
                                <x-ui.button size="sm" variant="ghost" class="-mr-3"
                                    :href="route('shop.book', ['slug' => $appointment->barbershop->slug, 'dienst' => $appointment->service_id, 'kapper' => $appointment->staff_member_id])">Opnieuw boeken</x-ui.button>
                            @endif
                        </x-slot:trailing>
                    </x-ui.list-item>
                @endforeach
            </x-ui.card>
        </x-ui.section>
    @endif
</div>
