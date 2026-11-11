{{-- Segmented control. options: array van waarde => label. wire:model gaat naar de radio's. --}}
@props(['options' => [], 'name' => 'segment', 'value' => null, 'label' => null])

@php($model = $attributes->whereStartsWith('wire:model'))

<div role="radiogroup" @if ($label) aria-label="{{ $label }}" @endif
    {{ $attributes->whereDoesntStartWith('wire:model')->class('flex rounded-input bg-surface p-1') }}>
    @foreach ($options as $optionValue => $optionLabel)
        <label class="relative flex-1 cursor-pointer">
            <input type="radio" class="peer sr-only" name="{{ $name }}" value="{{ $optionValue }}"
                @checked((string) $value === (string) $optionValue) {{ $model }} />
            <span class="flex min-h-11 items-center justify-center rounded-[0.5rem] px-2 text-sm font-medium whitespace-nowrap text-muted transition-[background-color,color,box-shadow] duration-200 ease-out-soft hover:text-text peer-checked:bg-card peer-checked:text-text peer-checked:shadow-soft peer-focus-visible:ring-2 peer-focus-visible:ring-[var(--focus)] lg:min-h-9">{{ $optionLabel }}</span>
        </label>
    @endforeach
</div>
