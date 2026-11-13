{{-- Gekleurd vierkant icoon voor een rij in een lijst, zoals in de instellingen van iOS. color: css-kleur. --}}
@props(['name', 'color' => '#8e8e93'])

<span {{ $attributes->class('flex size-8 items-center justify-center rounded-[0.5rem] text-white') }} style="background-color: {{ $color }}">
    <x-ui.icon :name="$name" size="18" />
</span>
