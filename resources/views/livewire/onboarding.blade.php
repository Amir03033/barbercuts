<div class="mx-auto max-w-md px-4 py-6">
    @if ($step < 6)
        <div class="flex items-center gap-2">
            @if ($step > 2)
                <x-ui.icon-button icon="chevron-left" label="Vorige stap" wire:click="back" class="-ml-3" />
            @endif
            <x-ui.progress :step="$step" :total="$total" class="flex-1" />
        </div>
    @endif

    {{-- Stap 1: account --}}
    @if ($step === 1)
        <form wire:submit="saveAccount" class="mt-6 space-y-4" novalidate>
            <div>
                <h1 class="text-lg font-bold">Meld je zaak aan</h1>
                <p class="mt-1 text-sm text-muted">Eerst je account. Binnen drie minuten staat je boekingspagina online.</p>
            </div>
            <x-ui.input label="Je naam" wire:model="name" autocomplete="name" required autofocus />
            <x-ui.input label="E-mailadres" type="email" wire:model="email" autocomplete="email" inputmode="email" required />
            <x-ui.input label="Wachtwoord" type="password" wire:model="password" autocomplete="new-password" hint="Minimaal 8 tekens." required />
            <x-ui.button type="submit" loading="saveAccount" full>Verder</x-ui.button>
            <div class="flex justify-center">
                <x-ui.button :href="route('login')" variant="ghost" size="sm" wire:navigate>Al een account? Inloggen</x-ui.button>
            </div>
        </form>
    @endif

    {{-- Stap 2: zaak --}}
    @if ($step === 2)
        <form wire:submit="saveShop" class="mt-6 space-y-4" novalidate>
            <div>
                <h1 class="text-lg font-bold">Je zaak</h1>
                <p class="mt-1 text-sm text-muted">Dit zien klanten op je boekingspagina.</p>
            </div>
            <x-ui.input label="Naam van je zaak" wire:model.live.debounce.400ms="shopName" autocomplete="organization" required autofocus />
            <x-ui.input label="Plaats" wire:model.blur="city" autocomplete="address-level2" required />
            <x-ui.phone-input wire:model="phone" label="Telefoonnummer van de zaak" />
            <x-ui.input label="Je boekingslink" wire:model.live.debounce.500ms="slug" autocapitalize="none" autocorrect="off" spellcheck="false"
                :hint="$slug !== '' ? 'barbercuts.nl/'.$slug.' is beschikbaar' : 'barbercuts.nl/naam-van-je-zaak'" required />
            <x-ui.button type="submit" loading="saveShop" full>Verder</x-ui.button>
        </form>
    @endif

    {{-- Stap 3: kappers --}}
    @if ($step === 3)
        <form wire:submit="saveBarbers" class="mt-6 space-y-4" novalidate>
            <div>
                <h1 class="text-lg font-bold">Werk je alleen of met meer kappers?</h1>
                <p class="mt-1 text-sm text-muted">Elke kapper krijgt een eigen agenda. Uitnodigen kan later.</p>
            </div>
            <x-ui.segmented label="Aantal kappers" wire:model.live="mode" :value="$mode" name="mode"
                :options="['solo' => 'Ik werk alleen', 'team' => 'Meer kappers']" />

            @if ($mode === 'team')
                <x-ui.card flat>
                    <x-ui.toggle label="Ik knip zelf ook" hint="Je krijgt dan een eigen agenda." wire:model.live="ownerCuts" :checked="$ownerCuts" />
                </x-ui.card>

                <div class="space-y-2">
                    @foreach ($barbers as $index => $barber)
                        <div class="flex items-end gap-2" wire:key="barber-{{ $index }}">
                            <div class="min-w-0 flex-1">
                                <x-ui.input label="Naam kapper {{ $index + 1 }}" wire:model="barbers.{{ $index }}" autocomplete="off" />
                            </div>
                            @if (count($barbers) > 1)
                                <x-ui.icon-button icon="x" label="Kapper verwijderen" wire:click="removeBarber({{ $index }})" />
                            @endif
                        </div>
                    @endforeach
                    <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="addBarber">Nog een kapper</x-ui.button>
                </div>
            @endif

            <x-ui.button type="submit" loading="saveBarbers" full>Verder</x-ui.button>
        </form>
    @endif

    {{-- Stap 4: tijden --}}
    @if ($step === 4)
        <form wire:submit="saveHours" class="mt-6 space-y-4" novalidate>
            <div>
                <h1 class="text-lg font-bold">Wanneer ben je open?</h1>
                <p class="mt-1 text-sm text-muted">Dit worden je openingstijden en de werktijden van elke kapper. Per kapper aanpassen kan later.</p>
            </div>
            <x-ui.week-hours :week="$week" :errors="$weekErrors" />
            @error('week')
                <p class="text-xs text-danger" role="alert">{{ $message }}</p>
            @enderror
            <x-ui.sticky-footer>
                <x-ui.button type="submit" loading="saveHours" full>Verder</x-ui.button>
            </x-ui.sticky-footer>
        </form>
    @endif

    {{-- Stap 5: diensten --}}
    @if ($step === 5)
        <form wire:submit="saveServices" class="mt-6 space-y-4" novalidate>
            <div>
                <h1 class="text-lg font-bold">Je diensten</h1>
                <p class="mt-1 text-sm text-muted">Zet aan wat je aanbiedt en pas de prijs aan. Later kun je alles wijzigen.</p>
            </div>

            @foreach ($services as $index => $service)
                <x-ui.card flat wire:key="service-{{ $index }}">
                    <x-ui.toggle :label="$service['name']" wire:model.live="services.{{ $index }}.selected" :checked="$service['selected']"
                        :hint="$service['selected'] ? null : 'Niet aanbieden'" />
                    @if ($service['selected'])
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <x-ui.input label="Minuten" type="text" inputmode="numeric" wire:model="services.{{ $index }}.duration" />
                            <x-ui.money-input label="Prijs" wire:model="services.{{ $index }}.price" />
                        </div>
                    @endif
                </x-ui.card>
            @endforeach

            @error('services')
                <p class="text-xs text-danger" role="alert">{{ $message }}</p>
            @enderror

            <x-ui.sticky-footer>
                <x-ui.button type="submit" loading="saveServices" full>Klaar</x-ui.button>
            </x-ui.sticky-footer>
        </form>
    @endif

    {{-- Stap 6: klaar --}}
    @if ($step === 6 && $shop)
        <div class="mt-6 space-y-5">
            <div class="text-center">
                <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-completed/10 text-completed">
                    <x-ui.icon name="check" />
                </span>
                <h1 class="mt-4 text-lg font-bold">{{ $shop->name }} staat online</h1>
                <p class="mt-1 text-sm text-muted">Deel je boekingslink. Klanten kunnen nu zelf een afspraak maken.</p>
            </div>

            <x-ui.copy-link :url="$shop->bookingUrl()" :label="$shop->bookingUrlLabel()" />

            <div class="grid grid-cols-2 gap-3">
                <x-ui.button variant="secondary" icon="message-circle" target="_blank" rel="noopener"
                    :href="'https://wa.me/?text='.rawurlencode('Maak je afspraak bij '.$shop->name.': '.$shop->bookingUrl())">WhatsApp</x-ui.button>
                <x-ui.button variant="secondary" icon="qr" :href="route('app.qr')" download>QR-code</x-ui.button>
            </div>

            <x-ui.button :href="route('app.today')" full>Naar mijn agenda</x-ui.button>
        </div>
    @endif
</div>
