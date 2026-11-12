{{-- Een zaak in de kappersgids: logo, naam, beoordeling, plaats en (als er gezocht is) de afstand. --}}
@props(['shop'])

@php
    $top = $shop->rating_count >= \App\Services\ReviewService::MIN_REVIEWS_FOR_TOP && $shop->rating_avg >= 4.5;
    $distance = $shop->distance_km ?? null;
@endphp

<a href="{{ route('shop.show', $shop->slug) }}" {{ $attributes->class('bc-lift flex items-center gap-3 rounded-card border border-border bg-card p-4 shadow-soft') }}>
    <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-card text-lg font-bold" style="background: {{ $shop->accent_color }}; color: {{ $shop->onAccentColor() }}">
        @if ($shop->logoUrl())
            <img src="{{ $shop->logoUrl() }}" alt="" class="size-full object-cover" width="56" height="56" loading="lazy" />
        @else
            {{ mb_strtoupper(mb_substr($shop->name, 0, 1)) }}
        @endif
    </span>
    <span class="min-w-0 flex-1">
        <span class="flex items-center gap-2">
            <span class="truncate text-base font-semibold text-text">{{ $shop->name }}</span>
            @if ($top)
                <x-ui.badge status="gold" class="shrink-0">Top beoordeeld</x-ui.badge>
            @endif
        </span>
        @if ($shop->hasRating())
            <span class="mt-0.5 flex items-center gap-1.5 text-sm">
                <x-ui.stars :rating="$shop->rating_avg" size="14" />
                <span class="font-semibold tabular-nums">{{ number_format($shop->rating_avg, 1, ',', '') }}</span>
                <span class="text-muted">({{ $shop->rating_count }})</span>
            </span>
        @else
            <span class="mt-0.5 block text-sm text-muted">Nog geen reviews</span>
        @endif
        <span class="mt-0.5 block truncate text-sm text-muted">
            {{ collect([$shop->address, $shop->city])->filter()->implode(', ') }}
            @if ($distance !== null)
                · {{ $distance < 1 ? max(100, (int) round($distance * 10) * 100).' m' : number_format($distance, 1, ',', '').' km' }}
            @endif
        </span>
    </span>
    <x-ui.icon name="chevron-right" class="shrink-0 text-muted" size="20" />
</a>
