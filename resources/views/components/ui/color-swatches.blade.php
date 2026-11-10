{{-- Kleurkeuze uit een vaste lijst (radio's). wire:model gaat naar de inputs. --}}
@props(['colors' => [], 'name' => 'color', 'value' => null, 'label' => 'Kleur'])

@php($model = $attributes->whereStartsWith('wire:model'))

<div role="radiogroup" aria-label="{{ $label }}" {{ $attributes->whereDoesntStartWith('wire:model')->class('flex flex-wrap gap-2') }}>
    @foreach ($colors as $color)
        <label class="relative cursor-pointer" wire:key="swatch-{{ $color }}">
            <input type="radio" class="peer sr-only" name="{{ $name }}" value="{{ $color }}" aria-label="{{ $color }}"
                @checked(strtolower((string) $value) === strtolower($color)) {{ $model }} />
            <span class="flex size-11 items-center justify-center rounded-full border-2 border-transparent text-transparent transition peer-checked:border-text peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-accent peer-focus-visible:ring-offset-2">
                <span class="flex size-8 items-center justify-center rounded-full" style="background-color: {{ $color }}">
                    <x-ui.icon name="check" size="16" />
                </span>
            </span>
        </label>
    @endforeach
</div>
