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

    @if ($loyalty->isNotEmpty())
        <x-ui.section title="Spaarkaarten">
            <div class="space-y-3">
                @foreach ($loyalty as $row)
                    <x-shop.loyalty-card :card="$row['card']" :stamps="$row['customer']->loyalty_stamps" :shop-name="$row['customer']->barbershop->name" wire:key="loyalty-{{ $row['customer']->id }}" />
                @endforeach
            </div>
        </x-ui.section>
    @endif

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

    <x-ui.section title="Meldingen">
        <x-push-toggle />
        <x-ui.card>
            <form wire:submit="savePreferences" class="space-y-1">
                <p class="pb-2 text-xs text-muted">Je krijgt elk bericht op één kanaal: push als dat aan staat op je apparaat, anders je eigen keuze hieronder.</p>
                <x-ui.toggle label="Pushmeldingen" hint="Op apparaten waar je ze hebt aangezet" wire:model="preferPush" />
                <x-ui.toggle label="E-mail" hint="Bevestigingen en herinneringen per mail" wire:model="preferMail" />
                @if ($smsAvailable)
                    <x-ui.toggle label="Sms" hint="Gebruik sms in plaats van e-mail (als de zaak sms aan heeft)" wire:model="preferSms" />
                @endif
                @if ($whatsappAvailable)
                    <x-ui.toggle label="WhatsApp" hint="Gebruik WhatsApp in plaats van e-mail (als de zaak dat aan heeft)" wire:model="preferWhatsapp" />
                @endif
                <div class="pt-3"><x-ui.button type="submit" variant="secondary" loading="savePreferences">Voorkeuren opslaan</x-ui.button></div>
            </form>
        </x-ui.card>
    </x-ui.section>
</div>
