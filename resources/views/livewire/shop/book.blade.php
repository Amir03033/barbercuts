@php
    $titles = ['service' => 'Kies een dienst', 'staff' => 'Kies een kapper', 'time' => 'Kies een dag en tijd', 'details' => 'Je gegevens'];
@endphp

<div>
    @if ($step === 'service')
        <x-ui.page-header :title="$titles[$step]" :subtitle="$shop->name" :back="route('shop.show', $shop->slug)" />
    @else
        <x-ui.page-header :title="$titles[$step]" :subtitle="$shop->name" back-action="back" />
    @endif

    <div class="px-4 py-4">
        {{-- Samenvatting van wat al gekozen is --}}
        @if ($service && $step !== 'service')
            <x-ui.card flat class="mb-5 space-y-1 !bg-surface text-sm">
                <p class="flex justify-between gap-3">
                    <span class="font-semibold">{{ $service->name }}</span>
                    <span class="shrink-0 text-muted tabular-nums">{{ duration_label($service->duration) }} · {{ money($service->price_cents) }}</span>
                </p>
                @if ($step !== 'staff' && $staffChoice !== '')
                    <p class="text-muted">{{ $staff ? 'Bij '.$staff->name : 'Geen voorkeur voor een kapper' }}</p>
                @endif
                @if ($step === 'details' && $start)
                    <p class="font-semibold">{{ ucfirst(nl_date($start, 'dddd D MMMM')) }} om {{ $start->format('H:i') }}</p>
                @endif
            </x-ui.card>
        @endif

        {{-- Stap 1: dienst --}}
        @if ($step === 'service')
            @if ($services->isEmpty())
                <x-ui.empty-state icon="scissors" title="Online boeken kan nog niet" text="Deze zaak heeft nog geen diensten. Bel de zaak voor een afspraak." />
            @else
                <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                    @foreach ($services as $option)
                        <x-ui.list-item wire:click="chooseService({{ $option->id }})" wire:key="service-{{ $option->id }}"
                            :title="$option->name" :subtitle="duration_label($option->duration).($option->description ? ' · '.$option->description : '')">
                            <x-slot:trailing><span class="font-semibold tabular-nums">{{ money($option->price_cents) }}</span></x-slot:trailing>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.card>
            @endif
        @endif

        {{-- Stap 2: kapper --}}
        @if ($step === 'staff')
            <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                <x-ui.list-item wire:click="chooseStaff('any')" title="Geen voorkeur" subtitle="De eerste kapper die vrij is">
                    <x-slot:leading>
                        <span class="flex size-11 items-center justify-center rounded-full bg-surface text-muted"><x-ui.icon name="users" size="20" /></span>
                    </x-slot:leading>
                </x-ui.list-item>
                @foreach ($staffOptions as $option)
                    <x-ui.list-item wire:click="chooseStaff('{{ $option->id }}')" wire:key="staff-{{ $option->id }}" :title="$option->name" :subtitle="$option->bio">
                        <x-slot:leading><x-ui.avatar :name="$option->name" :color="$option->color" /></x-slot:leading>
                    </x-ui.list-item>
                @endforeach
            </x-ui.card>
        @endif

        {{-- Stap 3: dag + tijd --}}
        @if ($step === 'time')
            <x-shop.slot-picker :days="$days" :times="$times" :date="$date" :time="$time" :can-load-more="$canLoadMore" />

            @error('time')
                <p class="mt-3 text-xs text-danger" role="alert">{{ $message }}</p>
            @enderror
            @error('booking')
                <x-ui.alert type="danger" class="mt-4">{{ $message }}</x-ui.alert>
            @enderror

            <x-ui.sticky-footer>
                <x-ui.button full wire:click="confirmTime" loading="confirmTime" :disabled="$time === ''">
                    {{ $time === '' ? 'Kies een tijd' : 'Verder met '.$time }}
                </x-ui.button>
            </x-ui.sticky-footer>
        @endif

        {{-- Stap 4: gegevens --}}
        @if ($step === 'details')
            @guest
                <x-ui.card flat class="mb-5">
                    <p class="text-base font-semibold">Sneller boeken met een account</p>
                    <p class="mt-1 text-sm text-muted">Al je afspraken op één plek · verplaatsen met één tik · je gegevens hoef je niet opnieuw in te vullen.</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <x-ui.button variant="secondary" size="sm" wire:click="continueWithAccount('login')">Inloggen</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" wire:click="continueWithAccount('register')">Account maken</x-ui.button>
                    </div>
                </x-ui.card>
            @endguest

            @if (! $user && ! $guestAllowed)
                <x-ui.alert>Bij {{ $shop->name }} boek je met een account. Log in of maak een account aan om verder te gaan.</x-ui.alert>
            @else
                <form wire:submit="book" class="space-y-4" novalidate>
                    @auth
                        <p class="text-sm text-muted">Je boekt als <span class="font-semibold text-text">{{ $user->name }}</span>.</p>
                    @endauth

                    <x-ui.input label="Naam" wire:model="name" autocomplete="name" required />
                    <x-ui.phone-input wire:model="phone" />
                    <x-ui.input label="E-mailadres" type="email" wire:model.blur="email" autocomplete="email" inputmode="email" optional
                        :hint="$email === '' ? 'Zonder e-mail ontvang je geen bevestiging per mail.' : 'Hier sturen we je bevestiging naartoe.'" />
                    <x-ui.textarea label="Opmerking" wire:model="notes" optional rows="2" />

                    {{-- Honeypot tegen bots: onzichtbaar voor mensen en schermlezers --}}
                    <div class="hidden" aria-hidden="true">
                        <label>Website <input type="text" class="text-base" wire:model="website" tabindex="-1" autocomplete="off" /></label>
                    </div>

                    @error('booking')
                        <x-ui.alert type="danger">{{ $message }}</x-ui.alert>
                    @enderror

                    <x-ui.sticky-footer>
                        <x-ui.button type="submit" loading="book" full>Bevestigen</x-ui.button>
                    </x-ui.sticky-footer>
                </form>
            @endif
        @endif
    </div>
</div>
