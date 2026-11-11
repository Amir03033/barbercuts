{{-- Avatar met foto of initialen. color: hex-kleur van de kapper (voor de agenda). --}}
@props(['name' => '', 'src' => null, 'color' => null, 'size' => 'md'])

@php
    $sizes = ['xs' => 'size-6 text-[0.625rem]', 'sm' => 'size-8 text-xs', 'md' => 'size-11 text-sm lg:size-10', 'lg' => 'size-16 text-lg'];
    $px = ['xs' => 24, 'sm' => 32, 'md' => 44, 'lg' => 64][$size] ?? 44;
    $initials = collect(preg_split('/\s+/', trim($name)) ?: [])
        ->filter()
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->pipe(fn ($parts) => $parts->count() > 1 ? $parts->first().$parts->last() : (string) $parts->first());
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" width="{{ $px }}" height="{{ $px }}" loading="lazy"
        {{ $attributes->class(['shrink-0 rounded-full object-cover ring-1 ring-black/5', $sizes[$size] ?? $sizes['md']]) }} />
@else
    <span role="img" aria-label="{{ $name }}"
        {{ $attributes->class(['inline-flex shrink-0 items-center justify-center rounded-full font-semibold ring-1 ring-black/5', $sizes[$size] ?? $sizes['md'], 'bg-surface text-text' => ! $color, 'text-white' => $color]) }}
        @if ($color) style="background-color: {{ $color }}" @endif>{{ $initials }}</span>
@endif
