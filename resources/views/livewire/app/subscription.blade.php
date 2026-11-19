@php
    $untilTrial = $shop->trial_ends_at?->isFuture() ? $shop->trial_ends_at : null;
    $untilFree = $shop->free_until?->isFuture() ? $shop->free_until : null;

    $isActive = $state === \App\Enums\AccessState::Active && $running;
    $isCancelled = $running?->status === \App\Enums\SubscriptionStatus::Cancelled;
    $isExpired = $state === \App\Enums\AccessState::Expired;
    $isPastDue = $state === \App\Enums\AccessState::PastDue;

    // De einddatum waar het om draait, en hoe lang de periode in totaal duurt (voor de ring).
    $endsAt = match (true) {
        $isActive => $running->current_period_ends_at,
        $state === \App\Enums\AccessState::Trial => $untilTrial,
        $state === \App\Enums\AccessState::Free => $untilFree,
        default => null,
    };
    $periodDays = match (true) {
        $isActive => 30,
        $state === \App\Enums\AccessState::Trial => max(1, (int) ($plan->trial_days ?? 14)),
        $state === \App\Enums\AccessState::Free => max(1, (int) ceil(($shop->created_at ?? now())->diffInDays($untilFree ?? now(), true))),
        default => 1,
    };
    $daysLeft = $endsAt ? max(0, (int) ceil(now()->diffInHours($endsAt, false) / 24)) : 0;
    $progress = $endsAt ? max(0, min(100, round(($daysLeft / $periodDays) * 100))) : 0;
    $urgent = $endsAt && $daysLeft <= 3 && ! $isActive;

    $tone = match (true) {
        $isExpired, $isPastDue, $state === \App\Enums\AccessState::Suspended => ['ring' => '#d70015', 'soft' => 'bg-no-show/10', 'text' => 'text-no-show'],
        $urgent => ['ring' => '#ff9f0a', 'soft' => 'bg-star/15', 'text' => 'text-gold-ink'],
        $isActive => ['ring' => '#248a3d', 'soft' => 'bg-completed/10', 'text' => 'text-completed'],
        default => ['ring' => '#0071e3', 'soft' => 'bg-scheduled/10', 'text' => 'text-scheduled'],
    };
    $circumference = 2 * M_PI * 54;
@endphp

@if (! $billing)
    {{-- Betalen via Mollie staat uit in het adminpanel: geen betaalschermen, wel uitleg. --}}
    <div>
        <x-ui.page-header title="Abonnement" :back="route('app.more')" />

        <div class="space-y-4 px-4 py-4">
            <x-ui.card flat class="space-y-3 text-center">
                <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-surface text-muted"><x-ui.icon name="credit-card" /></span>
                <div>
                    <x-ui.badge status="neutral">Tijdelijk uit</x-ui.badge>
                    <p class="mt-3 text-lg font-bold">Betalen staat tijdelijk uit</p>
                    <p class="mt-1 text-sm text-muted">Je gebruikt BarberCuts op dit moment gratis en hoeft niets te doen. Zodra betalen via Mollie aan gaat, lees je dat hier en krijg je ruim van tevoren bericht.</p>
                </div>
            </x-ui.card>
        </div>
    </div>
@else
<div>
    <x-ui.page-header title="Abonnement" :back="route('app.more')" />

    <div class="space-y-5 px-4 py-4 stagger">
        @if ($processing)
            <x-ui.alert title="We verwerken je betaling">Dit duurt meestal een paar seconden. Ververs de pagina als de status niet verandert.</x-ui.alert>
        @endif

        {{-- Hoe lang heb je nog? Een ring met de resterende dagen --}}
        <x-ui.card flat class="overflow-hidden !p-0 shadow-soft">
            <div class="flex items-center gap-5 p-5">
                @if ($endsAt)
                    <div class="relative size-28 shrink-0" x-data="{ offset: {{ round($circumference, 2) }} }" x-init="setTimeout(() => offset = {{ round($circumference * (1 - $progress / 100), 2) }}, 200)">
                        <svg viewBox="0 0 120 120" class="size-full -rotate-90" aria-hidden="true">
                            <circle cx="60" cy="60" r="54" fill="none" stroke="currentColor" stroke-width="9" class="text-border" />
                            <circle cx="60" cy="60" r="54" fill="none" stroke="{{ $tone['ring'] }}" stroke-width="9" stroke-linecap="round"
                                stroke-dasharray="{{ round($circumference, 2) }}" :stroke-dashoffset="offset" style="transition: stroke-dashoffset 1.2s cubic-bezier(0.22, 1, 0.36, 1)" />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-3xl leading-none font-bold tracking-tight tabular-nums" x-data="bcCount({{ $daysLeft }}, 900)" x-text="label">{{ $daysLeft }}</span>
                            <span class="mt-0.5 text-[0.6875rem] font-medium text-muted">{{ $daysLeft === 1 ? 'dag' : 'dagen' }} over</span>
                        </div>
                    </div>
                @else
                    <span class="flex size-28 shrink-0 items-center justify-center rounded-full {{ $tone['soft'] }} {{ $tone['text'] }}"><x-ui.icon :name="$isExpired || $isPastDue ? 'alert' : 'credit-card'" size="40" /></span>
                @endif

                <div class="min-w-0">
                    <x-ui.badge :status="$isActive ? 'completed' : ($isExpired || $isPastDue || $state === \App\Enums\AccessState::Suspended ? 'no_show' : 'scheduled')">{{ $state->label() }}</x-ui.badge>
                    <p class="mt-2 text-xl leading-6 font-bold tracking-tight">
                        @if ($isActive)
                            {{ $isCancelled ? 'Opgezegd' : 'Je abonnement loopt' }}
                        @elseif ($state === \App\Enums\AccessState::Trial)
                            {{ $urgent ? 'Je proefperiode is bijna voorbij' : 'Je proeft gratis' }}
                        @elseif ($state === \App\Enums\AccessState::Free)
                            Gratis gebruik
                        @elseif ($isPastDue)
                            Betaling mislukt
                        @elseif ($isExpired)
                            Je proefperiode is voorbij
                        @else
                            Geblokkeerd
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-muted">
                        @if ($isActive)
                            {{ $isCancelled ? 'Je kunt nog werken tot' : 'Betaald tot' }} <span class="font-semibold text-text">{{ nl_date($endsAt->setTimezone($shop->timezone), 'D MMMM YYYY') }}</span>.
                        @elseif ($state === \App\Enums\AccessState::Trial)
                            Tot en met <span class="font-semibold text-text">{{ nl_date($endsAt->setTimezone($shop->timezone), 'D MMMM YYYY') }}</span>. Daarna start je een abonnement om door te gaan.
                        @elseif ($state === \App\Enums\AccessState::Free)
                            Je gebruikt BarberCuts gratis tot <span class="font-semibold text-text">{{ nl_date($endsAt->setTimezone($shop->timezone), 'D MMMM YYYY') }}</span>.
                        @elseif ($isPastDue)
                            Je kunt nog even gewoon werken. Start je abonnement opnieuw om dit op te lossen.
                        @elseif ($isExpired)
                            Je kunt alles nog bekijken, maar niets wijzigen, en klanten kunnen niet online boeken tot je je abonnement start.
                        @else
                            Neem contact met ons op via {{ config('barbercuts.company.email') }}.
                        @endif
                    </p>
                </div>
            </div>

            {{-- Waar je nu bent: proef, abonnement, maandelijks --}}
            @php
                $step = match (true) {
                    $isActive => 2,
                    $state === \App\Enums\AccessState::Trial, $state === \App\Enums\AccessState::Free => 1,
                    default => 1,
                };
            @endphp
            <ol class="grid grid-cols-3 border-t border-border/70 bg-surface/60 text-center text-[0.6875rem] font-medium" aria-label="Voortgang">
                @foreach (['Proefperiode', 'Abonnement', 'Elke maand verlengd'] as $index => $label)
                    @php($done = $index + 1 < $step || ($index + 1 === $step && $isActive))
                    <li class="flex flex-col items-center gap-1.5 px-2 py-3 {{ $index + 1 === $step ? 'text-text' : 'text-muted' }}">
                        <span class="flex size-6 items-center justify-center rounded-full text-[0.6875rem] font-bold transition-colors {{ $done ? 'bg-completed text-white' : ($index + 1 === $step ? 'bg-accent text-on-accent' : 'bg-border text-muted') }}">
                            @if ($done)<x-ui.icon name="check" size="14" />@else{{ $index + 1 }}@endif
                        </span>
                        {{ $label }}
                    </li>
                @endforeach
            </ol>
        </x-ui.card>

        @if ($canStart && $plan)
            <div class="space-y-3">
                <x-ui.button full wire:click="start" loading="start">{{ $isExpired || $isPastDue ? 'Abonnement starten' : 'Alvast een abonnement starten' }} · {{ money($plan->price_cents) }} per maand</x-ui.button>
                <p class="text-xs text-muted">
                    Je betaalt de eerste maand met iDEAL en geeft toestemming voor een automatische maandelijkse incasso.
                    @if ($untilTrial || $untilFree)
                        Die maand gaat in na je {{ $untilTrial ? 'proefperiode' : 'gratis periode' }}, dus je betaalt nooit dubbel.
                    @endif
                </p>
            </div>
        @endif

        @if ($plan)
            <x-ui.card flat class="space-y-4">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-muted">{{ $plan->name }}</p>
                        <p class="text-display font-bold tracking-tight tabular-nums">{{ money($running?->price_cents ?? $plan->price_cents) }}</p>
                    </div>
                    <p class="pb-2 text-sm text-muted">per maand</p>
                </div>
                <ul class="grid gap-2 text-sm sm:grid-cols-2">
                    @foreach (['Onbeperkt kappers en agenda\'s', 'Onbeperkt afspraken en klanten', 'Eigen boekingspagina', 'Omzet en herinneringen', 'Reviews, spaarkaart en wachtlijst', 'Opzeggen kan altijd'] as $feature)
                        <li class="flex gap-2"><x-ui.icon name="check" size="18" class="shrink-0 text-completed" />{{ $feature }}</li>
                    @endforeach
                </ul>
            </x-ui.card>
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

        <p class="text-center text-xs text-muted">Vragen over je abonnement? <a href="mailto:{{ config('barbercuts.company.email') }}" class="font-medium underline">Mail ons</a>.</p>
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
@endif
