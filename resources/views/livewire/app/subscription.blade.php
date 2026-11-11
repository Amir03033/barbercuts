@php
    $untilTrial = $shop->trial_ends_at?->isFuture() ? $shop->trial_ends_at : null;
    $untilFree = $shop->free_until?->isFuture() ? $shop->free_until : null;
@endphp

<div>
    <x-ui.page-header title="Abonnement" :back="route('app.more')" />

    <div class="space-y-6 px-4 py-4">
        @if ($processing)
            <x-ui.alert title="We verwerken je betaling">Dit duurt meestal een paar seconden. Ververs de pagina als de status niet verandert.</x-ui.alert>
        @endif

        <x-ui.card flat class="space-y-2">
            <div class="flex items-center justify-between gap-3">
                <p class="text-base font-semibold">Status</p>
                <x-ui.badge :status="$state->value === 'active' ? 'completed' : ($state->value === 'expired' || $state->value === 'suspended' ? 'no_show' : 'scheduled')">{{ $state->label() }}</x-ui.badge>
            </div>

            @if ($state === \App\Enums\AccessState::Active && $running)
                <p class="text-sm text-muted">
                    {{ $running->status === \App\Enums\SubscriptionStatus::Cancelled ? 'Opgezegd. Je kunt nog werken tot' : 'Betaald tot' }}
                    <span class="font-semibold text-text">{{ nl_date($running->current_period_ends_at->setTimezone($shop->timezone), 'D MMMM YYYY') }}</span>.
                </p>
            @elseif ($state === \App\Enums\AccessState::Trial && $untilTrial)
                <p class="text-sm text-muted">Je proefperiode loopt tot <span class="font-semibold text-text">{{ nl_date($untilTrial->setTimezone($shop->timezone), 'D MMMM YYYY') }}</span>.</p>
            @elseif ($state === \App\Enums\AccessState::Free && $untilFree)
                <p class="text-sm text-muted">Je gebruikt BarberCuts gratis tot <span class="font-semibold text-text">{{ nl_date($untilFree->setTimezone($shop->timezone), 'D MMMM YYYY') }}</span>.</p>
            @elseif ($state === \App\Enums\AccessState::PastDue)
                <p class="text-sm text-muted">De laatste betaling is mislukt. Je kunt nog even gewoon werken; start je abonnement opnieuw om dit op te lossen.</p>
            @elseif ($state === \App\Enums\AccessState::Expired)
                <p class="text-sm text-muted">Je proefperiode is voorbij. Je kunt alles nog bekijken, maar niets wijzigen, en klanten kunnen niet online boeken tot je je abonnement start.</p>
            @elseif ($state === \App\Enums\AccessState::Suspended)
                <p class="text-sm text-muted">Je account is geblokkeerd. Neem contact met ons op via {{ config('barbercuts.company.email') }}.</p>
            @endif
        </x-ui.card>

        @if ($plan)
            <x-ui.card flat class="space-y-1">
                <p class="text-xs font-medium text-muted">Prijs</p>
                <p class="text-display font-bold tracking-tight tabular-nums">{{ money($running?->price_cents ?? $plan->price_cents) }}</p>
                <p class="text-sm text-muted">per maand · opzeggen kan altijd</p>
            </x-ui.card>
        @endif

        @if ($canStart && $plan)
            <div class="space-y-3">
                <x-ui.button full wire:click="start" loading="start">Abonnement starten · {{ money($plan->price_cents) }}</x-ui.button>
                <p class="text-xs text-muted">
                    Je betaalt de eerste maand met iDEAL en geeft toestemming voor een automatische maandelijkse incasso.
                    @if ($untilTrial || $untilFree)
                        Die maand gaat in na je {{ $untilTrial ? 'proefperiode' : 'gratis periode' }}, dus je betaalt nooit dubbel.
                    @endif
                </p>
            </div>
        @endif

        @if ($running && $running->status === \App\Enums\SubscriptionStatus::Active)
            <x-ui.button variant="secondary" full x-on:click="$dispatch('open-sheet', 'cancel-subscription')">Abonnement opzeggen</x-ui.button>
        @endif

        @if ($payments->isNotEmpty())
            <x-ui.section title="Betalingen">
                <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                    @foreach ($payments as $payment)
                        <x-ui.list-item wire:key="payment-{{ $payment->id }}" :chevron="false" :title="money($payment->amount_cents)"
                            :subtitle="nl_date($payment->created_at->setTimezone($shop->timezone), 'D MMM YYYY')">
                            <x-slot:trailing>
                                <x-ui.badge :status="$payment->status === 'paid' ? 'completed' : (in_array($payment->status, ['failed', 'canceled', 'expired'], true) ? 'no_show' : 'scheduled')">
                                    {{ match ($payment->status) { 'paid' => 'Betaald', 'failed' => 'Mislukt', 'canceled' => 'Geannuleerd', 'expired' => 'Verlopen', default => 'In behandeling' } }}
                                </x-ui.badge>
                            </x-slot:trailing>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.card>
            </x-ui.section>
        @endif
    </div>

    <x-ui.bottom-sheet name="cancel-subscription" title="Abonnement opzeggen?">
        <p class="text-sm text-muted">
            Je kunt BarberCuts blijven gebruiken tot het einde van de periode die je al betaald hebt
            @if ($running) ({{ nl_date($running->current_period_ends_at->setTimezone($shop->timezone), 'D MMMM YYYY') }})@endif.
            Daarna wordt er niets meer afgeschreven.
        </p>
        <div class="mt-4 space-y-3">
            <x-ui.button variant="danger" full wire:click="cancel" loading="cancel">Ja, opzeggen</x-ui.button>
            <x-ui.button variant="secondary" full x-on:click="$dispatch('close-sheet')">Nee, laat doorlopen</x-ui.button>
        </div>
    </x-ui.bottom-sheet>
</div>
