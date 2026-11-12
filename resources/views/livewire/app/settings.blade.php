@php
    use App\Livewire\App\Settings;
@endphp

<div>
    <x-ui.page-header title="Instellingen" :back="route('app.more')" />

    <form wire:submit="save" class="space-y-8 px-4 py-4" novalidate>
        <x-ui.section title="Online boeken">
            <x-ui.select label="Tijden tonen per" wire:model="slotInterval" :selected="$slotInterval" :options="Settings::SLOT_INTERVALS"
                hint="Bij 15 minuten kunnen klanten kiezen uit 09:00, 09:15, 09:30…" />
            <x-ui.select label="Minimaal vooraf boeken" wire:model="minNotice" :selected="$minNotice" :options="Settings::NOTICE_OPTIONS" />
            <x-ui.input label="Hoeveel dagen vooruit" type="text" inputmode="numeric" wire:model="maxDaysAhead" />
            <x-ui.card flat class="space-y-3">
                <x-ui.toggle label="Boeken zonder account" hint="Klanten vullen alleen naam en telefoonnummer in." wire:model="allowGuestBooking" :checked="$allowGuestBooking" />
                <x-ui.toggle label="Klant kiest de kapper" hint="Uit: wij verdelen de afspraken eerlijk." wire:model="allowStaffChoice" :checked="$allowStaffChoice" />
            </x-ui.card>
        </x-ui.section>

        <x-ui.section title="Wijzigen door de klant" text="Daarna moet de klant bellen.">
            <x-ui.select label="Annuleren kan" wire:model="cancelLimit" :selected="$cancelLimit" :options="Settings::LIMIT_OPTIONS" />
            <x-ui.select label="Verplaatsen kan" wire:model="rescheduleLimit" :selected="$rescheduleLimit" :options="Settings::LIMIT_OPTIONS" />
        </x-ui.section>

        <x-ui.section title="Herinneringen" text="Elke klant krijgt een bericht op één kanaal: push (als de klant een account en meldingen heeft), anders e-mail.">
            <x-ui.select label="1e herinnering" wire:model.live="reminder1" :selected="$reminder1"
                :options="Settings::REMINDER_1_OPTIONS + ['custom' => 'Anders…']" />
            @if ($reminder1 === 'custom')
                <x-ui.input label="Uren vooraf (1e herinnering)" type="text" inputmode="decimal" wire:model="reminder1Hours" />
            @endif

            <x-ui.select label="2e herinnering" wire:model.live="reminder2" :selected="$reminder2"
                :options="['off' => 'Uit'] + Settings::REMINDER_2_OPTIONS + ['custom' => 'Anders…']" />
            @if ($reminder2 === 'custom')
                <x-ui.input label="Uren vooraf (2e herinnering)" type="text" inputmode="decimal" wire:model="reminder2Hours" />
            @endif

            <x-ui.toggle label="Vraag na een bezoek om een review" hint="Een paar uur na een voltooide afspraak, hooguit één keer per maand per klant." wire:model="reviewRequests" :checked="$reviewRequests" />
            <x-ui.toggle label="Pushmeldingen aan klanten" hint="Voor klanten met een account die meldingen hebben aangezet" wire:model="channelPush" :checked="$channelPush" />
            @if (\App\Support\Features::sms())
                <x-ui.toggle label="Sms voor klanten zonder e-mailadres" hint="Kost per bericht" wire:model="smsEnabled" :checked="$smsEnabled" />
            @endif
            @if (\App\Support\Features::whatsapp())
                <x-ui.toggle label="WhatsApp voor klanten die dat kiezen" hint="Kost per bericht" wire:model="whatsappEnabled" :checked="$whatsappEnabled" />
            @endif
        </x-ui.section>

        <x-ui.sticky-footer nav>
            <x-ui.button type="submit" loading="save" full>Opslaan</x-ui.button>
        </x-ui.sticky-footer>
    </form>
</div>
