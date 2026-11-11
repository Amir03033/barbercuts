<x-filament-panels::page>
    <x-filament::section heading="Facturering">
        <p class="text-sm">
            Facturering staat <strong>{{ $this->billingEnabled() ? 'aan' : 'uit' }}</strong>.
            @if ($this->billingEnabled())
                Zaken betalen na hun proefperiode via Mollie.
            @else
                Het platform is gratis (gratis start). Zaken zien nergens betaalschermen. Zet facturering aan met de knop rechtsboven.
            @endif
        </p>
    </x-filament::section>

    <form wire:submit="save" class="space-y-6">
        <x-filament::section heading="Functies" description="Wat hier staat overschrijft de standaard uit .env.">
            <div class="space-y-3">
                <label class="flex items-center gap-3 text-sm"><x-filament::input.checkbox wire:model="deposits" /> Aanbetalingen (nog niet klaar voor gebruik, zie de opdracht)</label>
                <label class="flex items-center gap-3 text-sm"><x-filament::input.checkbox wire:model="sms" /> SMS-berichten (vanaf fase 5)</label>
                <label class="flex items-center gap-3 text-sm"><x-filament::input.checkbox wire:model="whatsapp" /> WhatsApp-berichten (vanaf fase 5)</label>
            </div>
        </x-filament::section>

        <x-filament::section heading="Gereserveerde slugs" description="Namen die geen zaak als boekingslink mag kiezen, naast de vaste lijst. Eén per regel.">
            <textarea wire:model="extraSlugs" rows="5" class="fi-input block w-full rounded-lg border border-gray-300 p-2 text-sm" aria-label="Extra gereserveerde slugs"></textarea>
        </x-filament::section>

        <x-filament::button type="submit">Opslaan</x-filament::button>
    </form>
</x-filament-panels::page>
