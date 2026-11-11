{{--
    Dropdown met zachte fade en slide. De trigger komt in de slot `trigger`; de menu-items (x-ui.dropdown-item) in de standaardslot.
    align: right (standaard) | left · placement: bottom (standaard) | top · width: Tailwind-breedte, bv. w-56.
--}}
@props(['align' => 'right', 'width' => 'w-56', 'label' => 'Menu', 'placement' => 'bottom'])

<div x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false" class="relative">
    <div x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu" aria-label="{{ $label }}">{{ $trigger }}</div>

    <div x-show="open" x-cloak role="menu" x-on:click="open = false"
        x-transition:enter="transition duration-150 ease-out-soft" x-transition:enter-start="-translate-y-1 scale-[0.98] opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100"
        x-transition:leave="transition duration-100 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="scale-[0.98] opacity-0"
        class="absolute z-40 {{ $width }} {{ $placement === 'top' ? 'bottom-full mb-2' : 'mt-2' }} {{ $align === 'left' ? 'left-0' : 'right-0' }} {{ $placement === 'top' ? ($align === 'left' ? 'origin-bottom-left' : 'origin-bottom-right') : ($align === 'left' ? 'origin-top-left' : 'origin-top-right') }} rounded-card border border-border bg-card p-1 shadow-pop">
        {{ $slot }}
    </div>
</div>
