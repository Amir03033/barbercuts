{{-- Paginakop: terugknop (back = url) + titel + optionele actie rechts (slot). --}}
@props(['title', 'back' => null, 'subtitle' => null])

<header {{ $attributes->class('sticky top-0 z-20 border-b border-border bg-bg/95 pt-safe backdrop-blur') }}>
    <div class="mx-auto flex min-h-14 max-w-2xl items-center gap-1 px-2">
        @if ($back)
            <a href="{{ $back }}" wire:navigate aria-label="Terug"
                class="flex size-11 shrink-0 items-center justify-center rounded-full text-text active:bg-surface">
                <x-ui.icon name="chevron-left" />
            </a>
        @endif
        <div @class(['min-w-0 flex-1', 'pl-2' => ! $back])>
            <h1 class="truncate text-base font-semibold text-text">{{ $title }}</h1>
            @if ($subtitle)
                <p class="truncate text-xs text-muted">{{ $subtitle }}</p>
            @endif
        </div>
        @if ($slot->isNotEmpty())
            <div class="flex shrink-0 items-center gap-1 pr-2">{{ $slot }}</div>
        @endif
    </div>
</header>
