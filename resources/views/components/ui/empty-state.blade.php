{{-- Lege staat: uitleg + één actie in de slot. --}}
@props(['title', 'text' => null, 'icon' => 'inbox'])

<div {{ $attributes->class('flex flex-col items-center px-6 py-12 text-center') }}>
    <span class="flex size-14 items-center justify-center rounded-full bg-surface text-muted">
        <x-ui.icon :name="$icon" />
    </span>
    <p class="mt-4 text-base font-semibold text-text">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-xs text-sm text-muted">{{ $text }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-6">{{ $slot }}</div>
    @endif
</div>
