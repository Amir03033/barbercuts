{{-- Status-chip voor afspraken: scheduled · completed · cancelled · no_show. Zonder slot wordt het Nederlandse label getoond. --}}
@props(['status' => 'scheduled'])

@php
    $map = [
        'scheduled' => ['Gepland', 'bg-scheduled/10 text-scheduled'],
        'completed' => ['Voltooid', 'bg-completed/10 text-completed'],
        'cancelled' => ['Geannuleerd', 'bg-cancelled/15 text-muted'],
        'no_show' => ['No-show', 'bg-no-show/10 text-no-show'],
        'neutral' => ['', 'bg-surface text-muted'],
    ];
    [$label, $classes] = $map[$status] ?? $map['neutral'];
@endphp

<span {{ $attributes->class("inline-flex items-center rounded-pill px-2.5 py-0.5 text-xs font-semibold $classes") }}>
    {{ $slot->isEmpty() ? $label : $slot }}
</span>
