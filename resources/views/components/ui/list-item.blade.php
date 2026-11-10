{{-- Rij in een lijst. Slots: leading (avatar/icoon), trailing (badge/bedrag). Met href wordt het een link met pijltje. --}}
@props(['href' => null, 'title' => null, 'subtitle' => null, 'chevron' => null])

@php
    $tag = $href ? 'a' : 'div';
    $chevron ??= (bool) $href;
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class(['flex min-h-14 items-center gap-3 px-4 py-3', 'active:bg-surface' => $href]) }}>
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
