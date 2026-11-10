{{--
    Knop. Varianten: primary (standaard) · secondary · ghost · danger. size: md (standaard) · sm.
    loading: naam van een Livewire-actie (toont een spinner tijdens die actie) of true (altijd bezig).
--}}
@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
    'loading' => false,
    'full' => false,
    'icon' => null,
    'size' => 'md',
])

@php
    $variants = [
        'primary' => 'bg-accent text-on-accent active:opacity-80',
        'secondary' => 'bg-surface text-text border border-border active:bg-border',
        'ghost' => 'bg-transparent text-text active:bg-surface',
        'danger' => 'bg-danger text-white active:opacity-80',
    ];

    $sizes = [
        'md' => 'min-h-12 px-5 text-base font-semibold',
        'sm' => 'min-h-11 px-3 text-sm font-medium',
    ];

    $classes = 'relative inline-flex items-center justify-center gap-2 rounded-card '.($sizes[$size] ?? $sizes['md']).' '
        .'select-none transition disabled:cursor-not-allowed disabled:opacity-50 '
        .($variants[$variant] ?? $variants['primary'])
        .($full ? ' w-full' : '');

    $target = is_string($loading) ? $loading : null;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" size="20" />
        @endif
        <span>{{ $slot }}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}
        @if ($loading === true) disabled aria-busy="true" @endif
        @if ($target) wire:loading.attr="disabled" wire:target="{{ $target }}" @endif>
        @if ($loading === true)
            <x-ui.icon name="loader" size="20" class="animate-spin" />
        @elseif ($target)
            <x-ui.icon name="loader" size="20" class="animate-spin" wire:loading wire:target="{{ $target }}" />
        @endif
        @if ($icon)
            <span class="inline-flex" @if ($target) wire:loading.remove wire:target="{{ $target }}" @endif><x-ui.icon :name="$icon" size="20" /></span>
        @endif
        <span>{{ $slot }}</span>
    </button>
@endif
