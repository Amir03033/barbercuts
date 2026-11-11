{{-- Status-chip voor afspraken: scheduled · completed · cancelled · no_show. Zonder slot wordt het Nederlandse label getoond. pulse: levende stip. --}}
@props(['status' => 'scheduled', 'pulse' => false])

@php
    $map = [
        'scheduled' => ['Gepland', 'bg-scheduled/10 text-scheduled'],
        'completed' => ['Voltooid', 'bg-completed/10 text-completed'],
        'cancelled' => ['Geannuleerd', 'bg-cancelled/15 text-muted'],
        'no_show' => ['No-show', 'bg-no-show/10 text-no-show'],
        'gold' => ['', 'bg-gold-soft text-gold-ink'],
        'neutral' => ['', 'bg-surface text-muted'],
    ];
    [$label, $classes] = $map[$status] ?? $map['neutral'];
@endphp

<span {{ $attributes->class("inline-flex items-center gap-1.5 rounded-pill px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap transition-colors duration-200 $classes") }}>
    <span @class(['size-1.5 rounded-full bg-current', 'animate-pulse-dot' => $pulse])></span>
    {{ $slot->isEmpty() ? $label : $slot }}
</span>
