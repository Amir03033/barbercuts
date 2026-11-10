{{-- Bedrag in euro's met decimaal toetsenbord. De server zet om naar centen. --}}
@props(['label' => null, 'name' => null, 'hint' => null, 'optional' => false])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id = $attributes->get('id') ?? 'f-'.str_replace(['.', '[', ']'], '-', (string) $name);
    $invalid = $name && $errors->has($name);
@endphp

<x-ui.field :label="$label" :for="$id" :name="$name" :hint="$hint" :optional="$optional">
    <div class="relative">
        <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-muted" aria-hidden="true">€</span>
        <input type="text" inputmode="decimal" autocomplete="off" placeholder="0,00" id="{{ $id }}" name="{{ $name }}"
            aria-invalid="{{ $invalid ? 'true' : 'false' }}"
            {{ $attributes->except('id')->class('bc-input pl-9 tabular-nums') }} />
    </div>
</x-ui.field>
