@props(['label' => null, 'name' => null, 'hint' => null, 'type' => 'text', 'optional' => false])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id = $attributes->get('id') ?? 'f-'.str_replace(['.', '[', ']'], '-', (string) $name);
    $invalid = $name && $errors->has($name);
@endphp

<x-ui.field :label="$label" :for="$id" :name="$name" :hint="$hint" :optional="$optional">
    <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}"
        aria-invalid="{{ $invalid ? 'true' : 'false' }}"
        @if ($invalid) aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->except('id')->class('bc-input') }} />
</x-ui.field>
