@props(['as' => 'div', 'padding' => true, 'flat' => false])

<{{ $as }} {{ $attributes->class([
    'rounded-card border border-border bg-bg',
    'p-4' => $padding,
    'shadow-soft' => ! $flat,
]) }}>
    {{ $slot }}
</{{ $as }}>
