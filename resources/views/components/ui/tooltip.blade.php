{{-- Tooltip bij zweven en focus. Alleen aanvullende uitleg; belangrijke informatie hoort altijd zichtbaar te zijn. --}}
@props(['text', 'position' => 'top'])

<span x-data="{ show: false }" x-on:mouseenter="show = true" x-on:mouseleave="show = false" x-on:focusin="show = true" x-on:focusout="show = false"
    class="relative inline-flex">
    {{ $slot }}
    <span x-show="show" x-cloak role="tooltip"
        x-transition:enter="transition duration-150 ease-out-soft" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="pointer-events-none absolute left-1/2 z-50 -translate-x-1/2 rounded-md bg-charcoal px-2 py-1 text-xs font-medium whitespace-nowrap text-white shadow-lift {{ $position === 'bottom' ? 'top-full mt-1.5' : 'bottom-full mb-1.5' }}">{{ $text }}</span>
</span>
