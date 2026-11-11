{{--
    Chip. Zonder `name` is het een knop of link (active = geselecteerd).
    Met `name` is het een keuze (radio, of checkbox met multiple) — wire:model gaat naar de input.
--}}
@props(['name' => null, 'value' => null, 'checked' => false, 'multiple' => false, 'active' => false, 'href' => null, 'disabled' => false])

@php
    $base = 'inline-flex min-h-11 items-center justify-center gap-1.5 whitespace-nowrap rounded-pill border px-4 text-sm font-medium select-none lg:min-h-9 '
        .'transition-[background-color,border-color,color,transform] duration-150 ease-out-soft active:scale-[0.97]';
    $idle = 'border-border-strong bg-card text-text hover:border-text/50 hover:bg-surface';
    $on = 'border-accent bg-accent text-on-accent';
    $model = $attributes->whereStartsWith('wire:model');
@endphp

@if ($name || $model->isNotEmpty())
    <label {{ $attributes->whereDoesntStartWith('wire:model')->class('relative inline-flex cursor-pointer') }}>
        <input type="{{ $multiple ? 'checkbox' : 'radio' }}" class="peer sr-only" value="{{ $value }}"
            @if ($name) name="{{ $name }}" @endif @checked($checked) @disabled($disabled) {{ $model }} />
        <span class="{{ $base }} {{ $idle }} peer-checked:border-accent peer-checked:bg-accent peer-checked:text-on-accent peer-focus-visible:ring-2 peer-focus-visible:ring-[var(--focus)] peer-focus-visible:ring-offset-2 peer-disabled:cursor-not-allowed peer-disabled:opacity-40 peer-disabled:line-through">{{ $slot }}</span>
    </label>
@elseif ($href)
    <a href="{{ $href }}" {{ $attributes->class([$base, $active ? $on : $idle]) }}
        @if ($active) aria-current="true" @endif>{{ $slot }}</a>
@else
    <button type="button" @disabled($disabled) aria-pressed="{{ $active ? 'true' : 'false' }}"
        {{ $attributes->class([$base, $active ? $on : $idle, 'disabled:opacity-40']) }}>{{ $slot }}</button>
@endif
