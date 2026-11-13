{{-- Kaart. padding: binnenruimte · flat: zonder schaduw · interactive: komt zacht omhoog bij zweven. --}}
@props(['as' => 'div', 'padding' => true, 'flat' => false, 'interactive' => false])

<{{ $as }} {{ $attributes->class([
    'rounded-card border border-border/70 bg-card',
    'p-4 lg:p-5' => $padding,
    'shadow-soft' => ! $flat,
    'bc-lift' => $interactive,
]) }}>
    {{ $slot }}
</{{ $as }}>
