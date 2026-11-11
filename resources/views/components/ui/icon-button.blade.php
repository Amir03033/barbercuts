{{-- Ronde knop met alleen een icoon. label is verplicht (voor schermlezers). --}}
@props(['icon', 'label', 'href' => null])

@php($classes = 'inline-flex size-11 shrink-0 items-center justify-center rounded-full text-muted transition-[background-color,color,transform] duration-150 ease-out-soft hover:bg-surface hover:text-text active:scale-95 lg:size-9')

@if ($href)
    <a href="{{ $href }}" aria-label="{{ $label }}" {{ $attributes->class($classes) }}><x-ui.icon :name="$icon" size="20" /></a>
@else
    <button type="button" aria-label="{{ $label }}" {{ $attributes->class($classes) }}><x-ui.icon :name="$icon" size="20" /></button>
@endif
