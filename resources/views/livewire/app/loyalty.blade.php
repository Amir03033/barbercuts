<div>
    <x-ui.page-header title="Spaarkaart" :back="route('app.more')" />

    <form wire:submit="save" class="space-y-6 px-4 py-4" novalidate>
        <x-ui.card flat class="space-y-4">
            <x-ui.toggle label="Spaarkaart aan" hint="Klanten krijgen automatisch een stempel bij elke voltooide afspraak en zien hun kaart bij hun afspraak."
                wire:model.live="active" :checked="$active" />
            <x-ui.input label="Naam van de kaart" wire:model="name" maxlength="60" required />
        </x-ui.card>

        <x-ui.section title="Sparen">
            <x-ui.card flat class="space-y-4">
                <div>
                    <p class="mb-2 text-sm font-medium">Hoeveel stempels voor een beloning?</p>
                    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Aantal stempels">
                        @foreach (\App\Models\LoyaltyCard::STAMP_OPTIONS as $option)
                            <x-ui.chip name="stamps" :value="$option" wire:model.live="stamps" :checked="(int) $stamps === $option">{{ $option }}</x-ui.chip>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="mb-2 text-sm font-medium">Welke diensten leveren een stempel op?</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($services as $service)
                            <x-ui.chip multiple :value="$service->id" wire:model="serviceIds" :checked="in_array((string) $service->id, $serviceIds, true)" wire:key="svc-{{ $service->id }}">{{ $service->name }}</x-ui.chip>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-muted">Niets gekozen = alle diensten.</p>
                </div>
            </x-ui.card>
        </x-ui.section>

        <x-ui.section title="Beloning">
            <x-ui.card flat class="space-y-4">
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Beloning">
                    <x-ui.chip name="rewardType" value="free" wire:model.live="rewardType" :checked="$rewardType === 'free'">Gratis afspraak</x-ui.chip>
                    <x-ui.chip name="rewardType" value="discount" wire:model.live="rewardType" :checked="$rewardType === 'discount'">Bedrag korting</x-ui.chip>
                </div>
                @if ($rewardType === 'discount')
                    <x-ui.money-input label="Korting" wire:model="rewardAmount" wire:key="reward-amount" />
                @endif
                <p class="text-sm text-muted">
                    Bij een volle kaart ziet de kapper bij het voltooien “Spaarkaart vol”. Eén tik verzilvert de beloning en past het bedrag aan.
                </p>
            </x-ui.card>
        </x-ui.section>

        <x-ui.alert>
            <p><strong>{{ $saving }}</strong> {{ $saving === 1 ? 'klant spaart' : 'klanten sparen' }} nu, waarvan <strong>{{ $full }}</strong> met een volle kaart.</p>
            <p class="mt-1">Had een klant al een papieren kaart? Zet de stempels over op de pagina van de klant.</p>
        </x-ui.alert>

        <x-ui.sticky-footer nav>
            <x-ui.button type="submit" loading="save" full>Opslaan</x-ui.button>
        </x-ui.sticky-footer>
    </form>
</div>
