{{-- Rij in een lijst. Slots: leading (avatar/icoon), trailing (badge/bedrag). Met href een link, met wire:click een knop. --}}
@props(['href' => null, 'title' => null, 'subtitle' => null, 'chevron' => null])

@php
    $clickable = $attributes->has('wire:click') || $attributes->has('x-on:click');
    $tag = $href ? 'a' : ($clickable ? 'button' : 'div');
    $chevron ??= $href || $clickable;
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @elseif ($clickable) type="button" @endif
    {{ $attributes->class(['flex min-h-14 items-center gap-3 px-4 py-3', 'active:bg-surface' => $href || $clickable, 'w-full text-left' => $clickable]) }}>
    @isset($leading)
        <div class="shrink-0">{{ $leading }}</div>
    @endisset

    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="truncate text-base font-medium text-text">{{ $title }}</p>
        @endif
        @if ($subtitle)
            <p class="truncate text-sm text-muted">{{ $subtitle }}</p>
        @endif
        {{ $slot }}
    </div>

    @isset($trailing)
        <div class="shrink-0 text-right">{{ $trailing }}</div>
    @endisset

    @if ($chevron)
        <x-ui.icon name="chevron-right" size="20" class="text-muted" />
    @endif
</{{ $tag }}>
