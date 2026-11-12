<div>
    @if ($rescheduling)
        <x-ui.page-header title="Afspraak verplaatsen" :subtitle="$shop->name" back-action="stopReschedule" />

        <div class="px-4 py-4">
            <x-ui.card flat class="mb-5 space-y-1 !bg-surface text-sm">
                <p class="font-semibold">{{ $appointment->service_name }} bij {{ $appointment->staffMember->name }}</p>
                <p class="text-muted">Nu: {{ nl_date($start, 'dddd D MMMM') }} om {{ $start->format('H:i') }}</p>
            </x-ui.card>

            <x-shop.slot-picker :days="$days" :times="$times" :date="$date" :time="$time" :can-load-more="$canLoadMore" />

            @error('time')
                <x-ui.alert type="danger" class="mt-4">{{ $message }}</x-ui.alert>
            @enderror

            <x-ui.sticky-footer>
                <x-ui.button full wire:click="reschedule" loading="reschedule" :disabled="$time === ''">
                    {{ $time === '' ? 'Kies een tijd' : 'Verplaats naar '.$time }}
                </x-ui.button>
            </x-ui.sticky-footer>
        </div>
    @else
        <x-ui.page-header :title="$shop->name" :back="route('shop.show', $shop->slug)" />

        <div class="space-y-6 px-4 py-4">
            @if ($justBooked && $appointment->isScheduled())
                <div class="text-center">
                    <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-completed/10 text-completed">
                        <x-ui.icon name="check" />
                    </span>
                    <h1 class="mt-3 text-lg font-bold">Je afspraak staat vast</h1>
                    <p class="mt-1 text-sm text-muted">
                        {{ $appointment->customer->email ? 'Je krijgt een bevestiging op '.$appointment->customer->email.'.' : 'Bewaar deze pagina: hier kun je je afspraak later wijzigen.' }}
                    </p>
                </div>
                <x-push-toggle title="Herinnering op je telefoon" text="Krijg een melding voor je afspraak en als er iets verandert." />
            @else
                <h1 class="text-lg font-bold">Je afspraak</h1>
            @endif

            <x-ui.card flat class="space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-lg font-bold">{{ ucfirst(nl_date($start, 'dddd D MMMM')) }}</p>
                        <p class="text-base tabular-nums">{{ $start->format('H:i') }} – {{ $start->addMinutes($appointment->duration)->format('H:i') }}</p>
                    </div>
                    <x-ui.badge :status="$appointment->status->value" />
                </div>
                <div class="border-t border-border pt-3 text-sm">
                    <p class="flex justify-between gap-3">
                        <span class="font-semibold">{{ $appointment->service_name }}</span>
                        <span class="shrink-0 tabular-nums">{{ money($appointment->price_cents) }}</span>
                    </p>
                    <p class="text-muted">Bij {{ $appointment->staffMember->name }} · {{ duration_label($appointment->duration) }}</p>
                    <p class="mt-2 text-muted">{{ $appointment->customer->name }}</p>
                    @if ($appointment->notes)
                        <p class="mt-1 text-muted">“{{ $appointment->notes }}”</p>
                    @endif
                </div>
            </x-ui.card>

            {{-- Spaarkaart van de klant bij deze zaak (fase 9) --}}
            @if ($loyaltyCard && $appointment->status !== \App\Enums\AppointmentStatus::Cancelled)
                <x-shop.loyalty-card :card="$loyaltyCard" :stamps="$appointment->customer->loyalty_stamps" />
            @endif

            {{-- Review na het bezoek (fase 7) --}}
            @if ($canReview)
                <x-ui.card flat wire:key="review-form">
                    <p class="text-base font-semibold">Hoe was het bij {{ $appointment->staffMember->name }}?</p>
                    <p class="mt-1 text-sm text-muted">Je review staat op de pagina van {{ $shop->name }}, met je voornaam.</p>
                    <form wire:submit="submitReview" class="mt-3 space-y-3" novalidate>
                        <div x-data="{ rating: $wire.entangle('rating') }" role="radiogroup" aria-label="Aantal sterren" class="flex gap-1">
                            @foreach (range(1, 5) as $star)
                                <button type="button" role="radio" :aria-checked="rating === {{ $star }}" aria-label="{{ $star }} {{ $star === 1 ? 'ster' : 'sterren' }}"
                                    x-on:click="rating = {{ $star }}" :class="rating >= {{ $star }} ? 'text-gold' : 'text-border-strong'"
                                    class="flex size-12 items-center justify-center rounded-full transition-[color,transform] duration-150 active:scale-90">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.12 2.12 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.12 2.12 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.12 2.12 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.12 2.12 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.12 2.12 0 0 0 1.597-1.16z"/></svg>
                                </button>
                            @endforeach
                        </div>
                        @error('rating')
                            <p class="text-sm text-danger">{{ $message }}</p>
                        @enderror
                        <x-ui.textarea label="Toelichting" wire:model="comment" optional maxlength="1000" />
                        <x-ui.button type="submit" loading="submitReview" full>Review plaatsen</x-ui.button>
                    </form>
                </x-ui.card>
            @elseif ($review)
                <x-ui.card flat wire:key="review-done">
                    <p class="text-base font-semibold">Jouw review</p>
                    <x-shop.review :review="$review" :shop-name="$shop->name" class="!pb-0" />
                </x-ui.card>
            @endif

            @if ($upcoming)
                <div class="space-y-3">
                    <x-ui.button :href="route('appointment.ics', $appointment->manage_token)" variant="secondary" icon="calendar" full>Zet in je agenda</x-ui.button>

                    @php($address = collect([$shop->address, trim($shop->postal_code.' '.$shop->city)])->filter()->implode(', '))
                    @if ($address !== '')
                        <x-ui.button :href="'https://maps.google.com/?q='.rawurlencode($shop->name.', '.$address)" target="_blank" rel="noopener" variant="secondary" icon="map-pin" full>Route</x-ui.button>
                    @endif

                    @if ($canReschedule)
                        <x-ui.button variant="secondary" full wire:click="startReschedule" loading="startReschedule">Verplaatsen</x-ui.button>
                    @endif
                    @if ($canCancel)
                        <x-ui.button variant="ghost" full x-on:click="$dispatch('open-sheet', 'cancel')">Annuleren</x-ui.button>
                    @endif

                    @if (! $canReschedule || ! $canCancel)
                        <x-ui.alert>
                            <p>Online wijzigen kan niet meer. Bel de zaak om je afspraak te wijzigen.</p>
                            @if ($shop->phone)
                                <x-ui.button :href="'tel:'.$shop->phone" variant="secondary" size="sm" icon="phone" class="mt-2">Bel {{ $shop->name }}</x-ui.button>
                            @endif
                        </x-ui.alert>
                    @endif
                </div>
            @elseif (! $appointment->isScheduled())
                <x-ui.button :href="route('shop.book', $shop->slug)" full>Nieuwe afspraak maken</x-ui.button>
            @endif

            {{-- Gast: laat rustig zien wat een account oplevert (§6.5) --}}
            @if ($showCreateAccount)
                <x-ui.card flat>
                    <p class="text-base font-semibold">Bewaar deze afspraak in je account</p>
                    <p class="mt-1 text-sm text-muted">Al je afspraken op één plek en de volgende keer hoef je niets meer in te vullen.</p>
                    <form wire:submit="createAccount" class="mt-4 space-y-3" novalidate>
                        <x-ui.input label="E-mailadres" type="email" wire:model="accountEmail" autocomplete="email" inputmode="email" />
                        <x-ui.input label="Kies een wachtwoord" type="password" wire:model="accountPassword" autocomplete="new-password" hint="Minimaal 8 tekens." />
                        <x-ui.button type="submit" variant="secondary" loading="createAccount" full>Account maken</x-ui.button>
                    </form>
                </x-ui.card>
            @elseif ($showSaveToAccount)
                <x-ui.button variant="secondary" full wire:click="saveToAccount" loading="saveToAccount">Bewaar in mijn account</x-ui.button>
            @elseif ($inAccount)
                <x-ui.button :href="route('account')" variant="ghost" full>Al mijn afspraken</x-ui.button>
            @endif
        </div>

        <x-ui.bottom-sheet name="cancel" title="Afspraak annuleren?">
            <p class="text-sm text-muted">{{ $appointment->service_name }} op {{ nl_date($start, 'dddd D MMMM') }} om {{ $start->format('H:i') }}. Dit kun je niet ongedaan maken.</p>
            <div class="mt-4 space-y-3">
                <x-ui.button variant="danger" full wire:click="cancel" loading="cancel">Ja, annuleer mijn afspraak</x-ui.button>
                <x-ui.button variant="secondary" full x-on:click="$dispatch('close-sheet')">Nee, laat staan</x-ui.button>
            </div>
        </x-ui.bottom-sheet>
    @endif
</div>
