{{--
    Bottom sheet. Openen: $dispatch('open-sheet', 'naam') (Alpine) of $this->dispatch('open-sheet', name: 'naam') (Livewire).
    Sluiten: $dispatch('close-sheet'), Escape of tik op de achtergrond.
--}}
@props(['name', 'title' => null])

<div x-data="{ open: false }" x-cloak
    x-on:open-sheet.window="if (($event.detail?.name ?? $event.detail) === @js($name)) open = true"
    x-on:close-sheet.window="open = false"
    x-on:keydown.escape.window="open = false">
    <div x-show="open" x-transition.opacity.duration.200ms class="fixed inset-0 z-50 bg-black/40" x-on:click="open = false"></div>

    <div x-show="open" x-trap.noscroll="open" role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif
        x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0" x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
        {{ $attributes->class('fixed inset-x-0 bottom-0 z-50 mx-auto max-h-[90dvh] max-w-2xl overflow-y-auto rounded-t-[1.25rem] bg-bg pb-safe shadow-soft') }}>
        <div class="sticky top-0 bg-bg px-4 pt-2 pb-3">
            <div class="mx-auto h-1 w-10 rounded-full bg-border" aria-hidden="true"></div>
            <div class="mt-3 flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-text">{{ $title }}</h2>
                <button type="button" x-on:click="open = false" aria-label="Sluiten"
                    class="-mr-2 flex size-11 items-center justify-center rounded-full text-muted active:bg-surface">
                    <x-ui.icon name="x" />
                </button>
            </div>
        </div>
        <div class="px-4 pb-6">{{ $slot }}</div>
    </div>
</div>
