{{-- Woordmerk (B3). Vervang de inhoud van dit component zodra er een logo is. --}}
@props(['href' => null])

@php($tag = $href ? 'a' : 'span')

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class('inline-flex min-h-11 items-center text-lg font-bold tracking-tight text-text') }}
    aria-label="BarberCuts">barbercuts</{{ $tag }}>
