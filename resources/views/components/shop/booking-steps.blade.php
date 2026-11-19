{{--
    Voortgang van het boeken: Dienst · Kapper · Tijd · Gegevens. De lijn vult zich zacht en de huidige stap krijgt een ring.
    step: de huidige stap van de boekflow (service, staff, time, waitlist of details).
--}}
@props(['step'])

@php
    $steps = ['service' => 'Dienst', 'staff' => 'Kapper', 'time' => 'Tijd', 'details' => 'Gegevens'];
    $keys = array_keys($steps);
    $current = array_search($step === 'waitlist' ? 'time' : $step, $keys, true);
    $current = $current === false ? 0 : $current;
    $fill = $current / (count($keys) - 1) * 100;
@endphp

<nav aria-label="Voortgang van je afspraak" {{ $attributes->class('px-4 pt-3') }}>
    <ol class="relative flex justify-between">
        {{-- Achtergrondlijn en de gevulde lijn --}}
        <span class="absolute top-3 right-3 left-3 h-0.5 rounded-full bg-border" aria-hidden="true"></span>
        <span class="absolute top-3 left-3 h-0.5 rounded-full bg-accent transition-[width] duration-500 ease-out-soft" style="width: calc((100% - 1.5rem) * {{ $fill / 100 }})" aria-hidden="true"></span>

        @foreach ($steps as $key => $label)
            @php($index = $loop->index)
            <li class="relative z-10 flex w-14 flex-col items-center gap-1.5" @if ($index === $current) aria-current="step" @endif>
                <span @class([
                    'flex size-6 items-center justify-center rounded-full text-[0.6875rem] font-bold transition-[background-color,color,box-shadow,transform] duration-300 ease-out-soft',
                    'bg-accent text-on-accent' => $index <= $current,
                    'bg-surface text-muted ring-1 ring-border' => $index > $current,
                    'scale-110 shadow-[0_0_0_4px_color-mix(in_srgb,var(--accent)_20%,transparent)]' => $index === $current,
                ])>
                    @if ($index < $current)
                        <x-ui.icon name="check" size="14" />
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <span @class(['text-[0.6875rem] font-medium transition-colors duration-300', 'text-text' => $index <= $current, 'text-muted' => $index > $current])>{{ $label }}</span>
            </li>
        @endforeach
    </ol>
</nav>
