{{-- options: array van waarde => label. Of geef zelf <option>-elementen mee in de slot. --}}
@props(['label' => null, 'name' => null, 'hint' => null, 'options' => [], 'selected' => null, 'placeholder' => null, 'optional' => false])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id = $attributes->get('id') ?? 'f-'.str_replace(['.', '[', ']'], '-', (string) $name);
    $invalid = $name && $errors->has($name);
@endphp

<x-ui.field :label="$label" :for="$id" :name="$name" :hint="$hint" :optional="$optional">
    <div class="relative">
        <select id="{{ $id }}" name="{{ $name }}" aria-invalid="{{ $invalid ? 'true' : 'false' }}"
            {{ $attributes->except('id')->class('bc-input pr-10') }}>
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
            @endforeach
            {{ $slot }}
        </select>
        <x-ui.icon name="chevron-down" size="20" class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-muted" />
    </div>
</x-ui.field>
