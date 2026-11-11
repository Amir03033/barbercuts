{{-- Afspraakkaart: tijd, klant, dienst, kapper (kleurbolletje) en status. Tikken opent de acties (selectAppointment). --}}
@props(['appointment', 'timezone', 'showStaff' => true, 'highlight' => false])

@php
    $start = $appointment->starts_at->toImmutable()->setTimezone($timezone);
    $end = $appointment->ends_at->toImmutable()->setTimezone($timezone);
@endphp

<button type="button" wire:click="selectAppointment({{ $appointment->id }})"
    {{ $attributes->class([
        'flex w-full items-center gap-3 rounded-card border p-3 text-left active:bg-surface',
        'border-text bg-card shadow-lift' => $highlight,
        'border-border bg-card shadow-soft' => ! $highlight,
        'opacity-60' => in_array($appointment->status->value, ['cancelled', 'no_show'], true),
    ]) }}>
    <span class="w-12 shrink-0 text-center">
        <span class="block text-base font-bold tabular-nums">{{ $start->format('H:i') }}</span>
        <span class="block text-xs text-muted tabular-nums">{{ $end->format('H:i') }}</span>
    </span>
    <span class="h-10 w-1 shrink-0 rounded-pill" style="background-color: {{ $appointment->staffMember->color }}" aria-hidden="true"></span>
    <span class="min-w-0 flex-1">
        <span class="block truncate text-base font-medium">{{ $appointment->customer->name }}</span>
        <span class="block truncate text-sm text-muted">
            {{ $appointment->service_name }}@if ($showStaff) · {{ $appointment->staffMember->name }}@endif
        </span>
    </span>
    @if ($appointment->status->value !== 'scheduled')
        <x-ui.badge :status="$appointment->status->value" />
    @endif
</button>
