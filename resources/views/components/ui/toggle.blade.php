{{-- Schakelaar met label. wire:model gaat naar de checkbox. --}}
@props(['label', 'hint' => null, 'name' => null, 'checked' => false])

@php($model = $attributes->whereStartsWith('wire:model'))

<label {{ $attributes->whereDoesntStartWith('wire:model')->class('relative flex min-h-11 cursor-pointer items-center justify-between gap-4') }}>
    <span class="min-w-0">
        <span class="block text-base font-medium text-text lg:text-sm">{{ $label }}</span>
        @if ($hint)
            <span class="block text-xs text-muted">{{ $hint }}</span>
        @endif
    </span>
    <input type="checkbox" class="peer sr-only" @if ($name) name="{{ $name }}" @endif @checked($checked) {{ $model }} />
    <span aria-hidden="true"
        class="relative h-7 w-12 shrink-0 rounded-pill bg-border-strong transition-colors duration-200 ease-out-soft peer-checked:bg-gold peer-focus-visible:ring-2 peer-focus-visible:ring-[var(--focus)] peer-focus-visible:ring-offset-2 after:absolute after:top-0.5 after:left-0.5 after:size-6 after:rounded-full after:bg-white after:shadow-soft after:transition-transform after:duration-200 after:ease-out-soft peer-checked:after:translate-x-5"></span>
</label>
