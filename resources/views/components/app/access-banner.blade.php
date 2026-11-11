{{--
    Banner bovenaan /app over de toegangsstatus (§7.7): proefperiode bijna voorbij, betaling mislukt of alleen-lezen.
    Bij facturering uit (gratis start) komt er nooit een banner.
--}}
@props(['shop', 'isOwner' => false])

@php
    $state = $shop->accessState();
    $days = $shop->trialDaysLeft();
    $billing = \App\Support\Features::billing();
@endphp

@if ($billing)
    @if ($state === \App\Enums\AccessState::PastDue)
        <div role="alert" class="bg-danger px-4 py-3 text-sm text-white">
            <p class="mx-auto flex max-w-2xl items-center justify-between gap-3">
                <span><strong>Betaling mislukt.</strong> Je kunt nog even gewoon werken.</span>
                @if ($isOwner)
                    <a href="{{ route('app.subscription') }}" wire:navigate class="shrink-0 font-semibold underline">Oplossen</a>
                @endif
            </p>
        </div>
    @elseif ($state === \App\Enums\AccessState::Expired)
        <div role="alert" class="bg-amber-100 px-4 py-3 text-sm text-amber-950">
            <p class="mx-auto flex max-w-2xl items-center justify-between gap-3">
                <span><strong>Alleen lezen.</strong> Je proefperiode is voorbij; klanten kunnen niet online boeken.</span>
                <a href="{{ $isOwner ? route('app.subscription') : '#' }}" @if ($isOwner) wire:navigate @endif class="shrink-0 font-semibold underline">{{ $isOwner ? 'Abonnement starten' : 'Vraag de eigenaar' }}</a>
            </p>
        </div>
    @elseif ($state === \App\Enums\AccessState::Trial && $days !== null && $days <= 7)
        <div class="bg-surface px-4 py-2 text-xs text-muted">
            <p class="mx-auto flex max-w-2xl items-center justify-between gap-3">
                <span>Proefperiode: nog {{ $days }} {{ $days === 1 ? 'dag' : 'dagen' }}.</span>
                @if ($isOwner)
                    <a href="{{ route('app.subscription') }}" wire:navigate class="shrink-0 font-semibold text-text underline">Abonnement</a>
                @endif
            </p>
        </div>
    @endif
@endif
