{{-- Paginakop: terugknop (back = url, of backAction = Livewire-actie) + titel + optionele actie rechts (slot). --}}
@props(['title', 'back' => null, 'backAction' => null, 'subtitle' => null])

<header x-data="{ scrolled: false }" x-on:scroll.window.passive="scrolled = window.scrollY > 4"
    :class="scrolled ? 'border-border' : 'border-transparent'"
    {{ $attributes->class('sticky top-0 z-20 border-b bg-bg/85 pt-safe backdrop-blur-xl transition-colors duration-200 lg:static lg:border-transparent! lg:bg-transparent lg:backdrop-blur-none') }}>
    <div class="flex min-h-14 items-center gap-1 px-2 lg:min-h-0 lg:px-4 lg:pt-8 lg:pb-2">
        @if ($backAction)
            <button type="button" wire:click="{{ $backAction }}" aria-label="Terug"
                class="flex size-11 shrink-0 items-center justify-center rounded-full text-text transition-[background-color,transform] duration-150 hover:bg-surface active:scale-95 lg:size-9">
                <x-ui.icon name="chevron-left" />
            </button>
        @elseif ($back)
            <a href="{{ $back }}" wire:navigate aria-label="Terug"
                class="flex size-11 shrink-0 items-center justify-center rounded-full text-text transition-[background-color,transform] duration-150 hover:bg-surface active:scale-95 lg:size-9">
                <x-ui.icon name="chevron-left" />
            </a>
        @endif
        <div @class(['min-w-0 flex-1', 'pl-2' => ! $back && ! $backAction, 'lg:pl-0' => ! $back && ! $backAction])>
            <h1 class="truncate text-base font-semibold text-text lg:text-xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="truncate text-xs text-muted">{{ $subtitle }}</p>
            @endif
        </div>
        @if ($slot->isNotEmpty())
            <div class="flex shrink-0 items-center gap-1 pr-2 lg:pr-0">{{ $slot }}</div>
        @endif
    </div>
</header>
