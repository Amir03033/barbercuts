{{--
    Tijden als chips. slots: ['09:00', '09:15', …] of gegroepeerd: ['Ochtend' => [...], 'Middag' => [...]].
    wire:model gaat naar de radio's.
--}}
@props(['slots' => [], 'name' => 'time', 'selected' => null])

@php
    $model = $attributes->whereStartsWith('wire:model');
    $groups = array_is_list($slots) ? ['' => $slots] : $slots;
@endphp

<div role="radiogroup" aria-label="Kies een tijd" {{ $attributes->whereDoesntStartWith('wire:model')->class('space-y-4') }}>
    @foreach ($groups as $group => $times)
        @continue(empty($times))
        <div wire:key="group-{{ $group }}">
            @if ($group !== '')
                <p class="mb-2 text-xs font-semibold tracking-wide text-muted uppercase">{{ $group }}</p>
            @endif
            <div class="grid grid-cols-4 gap-2">
                @foreach ($times as $time)
                    <label class="relative cursor-pointer" wire:key="time-{{ $time }}">
                        <input type="radio" class="peer sr-only" name="{{ $name }}" value="{{ $time }}"
                            @checked($selected === $time) {{ $model }} />
                        <span class="flex min-h-11 items-center justify-center rounded-pill border border-border bg-bg text-sm font-medium text-text tabular-nums transition peer-checked:border-accent peer-checked:bg-accent peer-checked:text-on-accent peer-focus-visible:ring-2 peer-focus-visible:ring-accent peer-focus-visible:ring-offset-2">{{ $time }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
