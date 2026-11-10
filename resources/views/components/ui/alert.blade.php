{{-- Melding in de pagina. type: info | warning | danger. --}}
@props(['type' => 'info', 'title' => null])

@php
    $styles = [
        'info' => 'border-border bg-surface text-text',
        'warning' => 'border-amber-300 bg-amber-50 text-amber-900',
        'danger' => 'border-red-200 bg-red-50 text-red-800',
    ];
@endphp

<div role="{{ $type === 'info' ? 'note' : 'alert' }}" {{ $attributes->class(['flex gap-3 rounded-card border p-4 text-sm', $styles[$type] ?? $styles['info']]) }}>
    <x-ui.icon name="alert" size="20" class="mt-0.5" />
    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        {{ $slot }}
    </div>
</div>
