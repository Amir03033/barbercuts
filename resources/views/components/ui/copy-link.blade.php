{{-- Toont een link met een kopieerknop. Na kopiëren: toast + optioneel een Livewire-actie (action). --}}
@props(['url', 'label' => null, 'action' => null])

<div x-data="{ copy() { navigator.clipboard?.writeText(@js($url)); $dispatch('toast', 'Link gekopieerd'); @if ($action) $wire.{{ $action }}(); @endif } }"
    {{ $attributes->class('flex items-center gap-2 rounded-card border border-border bg-surface py-1 pr-1 pl-4') }}>
    <span class="min-w-0 flex-1 truncate text-base font-medium text-text">{{ $label ?? $url }}</span>
    <x-ui.button variant="secondary" size="sm" x-on:click="copy()">Kopiëren</x-ui.button>
</div>
