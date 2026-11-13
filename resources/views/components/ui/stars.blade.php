{{-- Sterren bij een beoordeling (1–5, afgerond op hele sterren). size in px. --}}
@props(['rating' => 0, 'size' => 16])

@php($filled = (int) round((float) $rating))

<span {{ $attributes->class('inline-flex items-center gap-0.5 align-middle') }} role="img" aria-label="{{ number_format((float) $rating, 1, ',', '') }} van 5 sterren">
    @foreach (range(1, 5) as $star)
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor" @class(['text-star' => $star <= $filled, 'text-border-strong' => $star > $filled])>
            <path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.12 2.12 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.12 2.12 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.12 2.12 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.12 2.12 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.12 2.12 0 0 0 1.597-1.16z"/>
        </svg>
    @endforeach
</span>
