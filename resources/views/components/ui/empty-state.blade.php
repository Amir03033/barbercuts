{{-- Lege staat: uitleg + één actie in de slot. --}}
@props(['title', 'text' => null, 'icon' => 'inbox'])

<div {{ $attributes->class('flex flex-col items-center px-6 py-12 text-center') }}>
    <span class="relative flex size-14 items-center justify-center rounded-full bg-gold-soft text-gold-ink">
        <span class="absolute inset-0 rounded-full ring-8 ring-gold-soft/50"></span>
        <x-ui.icon :name="$icon" class="relative" />
    </span>
    <p class="mt-5 text-base font-semibold text-text">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-xs text-sm text-muted">{{ $text }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-6">{{ $slot }}</div>
    @endif
</div>
