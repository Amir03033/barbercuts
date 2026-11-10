{{-- Knop om een bestand te kiezen. wire:model gaat naar de (verborgen) file-input. --}}
@props(['accept' => 'image/*', 'multiple' => false, 'icon' => 'image', 'name' => null])

@php
    $model = $attributes->whereStartsWith('wire:model');
    $name ??= $model->first();
@endphp

<div {{ $attributes->whereDoesntStartWith('wire:model') }}>
    <label class="relative inline-flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-card border border-border bg-surface px-3 text-sm font-medium text-text active:bg-border has-focus-visible:ring-2 has-focus-visible:ring-accent">
        <x-ui.icon :name="$icon" size="20" wire:loading.remove wire:target="{{ $name }}" />
        <x-ui.icon name="loader" size="20" class="animate-spin" wire:loading wire:target="{{ $name }}" />
        <span>{{ $slot }}</span>
        <input type="file" class="sr-only" accept="{{ $accept }}" @if ($multiple) multiple @endif {{ $model }} />
    </label>
    @if ($name)
        @error($name)
            <p class="mt-1.5 text-xs text-danger" role="alert">{{ $message }}</p>
        @enderror
        @error($name.'.*')
            <p class="mt-1.5 text-xs text-danger" role="alert">{{ $message }}</p>
        @enderror
    @endif
</div>
