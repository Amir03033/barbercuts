{{--
    Sheet op mobiel, dialoog op desktop (ook gebruikt als modal: <x-ui.modal>).
    Openen: $dispatch('open-sheet', 'naam') (Alpine) of $this->dispatch('open-sheet', name: 'naam') (Livewire).
    Sluiten: $dispatch('close-sheet'), Escape of tik op de achtergrond.
--}}
@props(['name', 'title' => null, 'size' => 'md'])

@php($width = ['sm' => 'lg:max-w-sm', 'md' => 'lg:max-w-md', 'lg' => 'lg:max-w-xl'][$size] ?? 'lg:max-w-md')

<div x-data="{ open: false }" x-cloak
    x-on:open-sheet.window="if (($event.detail?.name ?? $event.detail) === @js($name)) open = true"
    x-on:close-sheet.window="open = false"
    x-on:keydown.escape.window="open = false">
    <div x-show="open" x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="animate-fade-in fixed inset-0 z-50 bg-ink/40 backdrop-blur-[3px]" x-on:click="open = false"></div>

    <div x-show="open" x-trap.noscroll="open" role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif
        x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-y-4"
        {{ $attributes->class("fixed inset-x-0 bottom-0 z-50 mx-auto max-h-[90dvh] max-w-2xl animate-sheet-up overflow-y-auto rounded-t-[1.5rem] border border-border bg-card pb-safe shadow-pop lg:inset-x-auto lg:top-1/2 lg:bottom-auto lg:left-1/2 lg:w-full lg:-translate-x-1/2 lg:-translate-y-1/2 lg:animate-dialog-in lg:rounded-[1.25rem] {$width}") }}>
        <div class="sticky top-0 z-10 bg-card/95 px-4 pt-2 pb-3 backdrop-blur lg:px-6 lg:pt-5">
            <div class="mx-auto h-1 w-10 rounded-full bg-border-strong lg:hidden" aria-hidden="true"></div>
            <div class="mt-3 flex items-center justify-between gap-2 lg:mt-0">
                <h2 class="text-lg font-semibold tracking-tight text-text">{{ $title }}</h2>
                <button type="button" x-on:click="open = false" aria-label="Sluiten"
                    class="-mr-2 flex size-11 items-center justify-center rounded-full text-muted transition-colors duration-150 hover:bg-surface hover:text-text active:scale-95 lg:size-9">
                    <x-ui.icon name="x" />
                </button>
            </div>
        </div>
        <div class="px-4 pb-6 lg:px-6">{{ $slot }}</div>
    </div>
</div>
