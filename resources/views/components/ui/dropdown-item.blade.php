{{-- Rij in een dropdown. Met href een link, anders een knop (wire:click / x-on:click). danger: rode tekst. --}}
@props(['href' => null, 'icon' => null, 'danger' => false])

@php($classes = 'flex min-h-11 w-full items-center gap-2.5 rounded-[0.6rem] px-3 text-left text-sm transition-colors duration-150 lg:min-h-9 '.($danger ? 'text-danger hover:bg-danger/10' : 'text-text hover:bg-surface'))

@if ($href)
    <a href="{{ $href }}" role="menuitem" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" size="18" class="text-muted" />@endif
        <span>{{ $slot }}</span>
    </a>
@else
    <button type="{{ $attributes->get('type', 'button') }}" role="menuitem" {{ $attributes->except('type')->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" size="18" class="text-muted" />@endif
        <span>{{ $slot }}</span>
    </button>
@endif
