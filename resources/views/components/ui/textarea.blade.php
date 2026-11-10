@props(['label' => null, 'name' => null, 'hint' => null, 'rows' => 3, 'optional' => false])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id = $attributes->get('id') ?? 'f-'.str_replace(['.', '[', ']'], '-', (string) $name);
    $invalid = $name && $errors->has($name);
@endphp

<x-ui.field :label="$label" :for="$id" :name="$name" :hint="$hint" :optional="$optional">
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
        aria-invalid="{{ $invalid ? 'true' : 'false' }}"
        {{ $attributes->except('id')->class('bc-input') }}>{{ $slot }}</textarea>
</x-ui.field>
