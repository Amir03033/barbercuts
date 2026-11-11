{{--
    Knop. Varianten: primary (standaard) · secondary · ghost · danger · gold. size: md (standaard) · sm.
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
        'primary' => 'bg-accent text-on-accent shadow-[inset_0_1px_0_rgb(255_255_255/0.14),0_1px_2px_rgb(18_18_20/0.28)] hover:bg-[color-mix(in_srgb,var(--accent)_88%,white)] active:scale-[0.985]',
        'secondary' => 'bg-card text-text border border-border-strong shadow-soft hover:bg-surface hover:border-text/40 active:scale-[0.985]',
        'ghost' => 'bg-transparent text-text hover:bg-surface active:bg-border/70',
        'danger' => 'bg-danger text-white shadow-[inset_0_1px_0_rgb(255_255_255/0.14)] hover:brightness-110 active:scale-[0.985]',
        'gold' => 'bg-gold text-ink shadow-[inset_0_1px_0_rgb(255_255_255/0.3),0_1px_2px_rgb(18_18_20/0.2)] hover:brightness-105 active:scale-[0.985]',
    ];

    $sizes = [
        'md' => 'min-h-12 px-5 text-base font-semibold lg:min-h-10 lg:px-4 lg:text-sm',
        'sm' => 'min-h-11 px-3 text-sm font-medium lg:min-h-9',
    ];

    $classes = 'relative inline-flex items-center justify-center gap-2 rounded-input select-none '
        .'transition-[background-color,box-shadow,transform,border-color,filter] duration-150 ease-out-soft '
        .'disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100 '
        .($sizes[$size] ?? $sizes['md']).' '
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
