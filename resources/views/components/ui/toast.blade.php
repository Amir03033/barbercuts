{{--
    Toasts (3 sec). Eén keer in de layout plaatsen.
    Tonen: $dispatch('toast', 'Opgeslagen') · $this->dispatch('toast', message: 'Opgeslagen') · session()->flash('toast', '…').
    nav = true: boven de bottom nav (alleen op mobiel).
--}}
@props(['nav' => false])

<div x-data="{
        items: [],
        add(detail) {
            const message = typeof detail === 'string' ? detail : (detail?.message ?? detail?.[0]?.message ?? '');
            if (! message) return;
            const id = Date.now() + Math.random();
            this.items.push({ id, message, type: detail?.type ?? 'default' });
            setTimeout(() => this.items = this.items.filter(item => item.id !== id), 3200);
        },
    }"
    x-init="@js(session('toast')) && add(@js(session('toast')))"
    x-on:toast.window="add($event.detail)"
    aria-live="polite" aria-atomic="true"
    class="pointer-events-none fixed inset-x-0 z-[60] flex flex-col items-center gap-2 px-4 lg:inset-x-auto lg:right-6 lg:items-end lg:px-0"
    style="bottom: calc({{ $nav ? 'var(--spacing-nav) + ' : '' }}env(safe-area-inset-bottom) + 1rem)">
    <template x-for="item in items" :key="item.id">
        <div role="status"
            x-transition:enter="transition duration-300 ease-out-soft" x-transition:enter-start="translate-y-3 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100"
            x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="translate-y-1 opacity-0"
            class="pointer-events-auto flex max-w-sm items-center gap-3 rounded-input px-4 py-3 text-sm font-medium shadow-pop"
            :class="item.type === 'error' ? 'bg-danger text-white' : 'bg-charcoal text-white'">
            <span class="size-2 shrink-0 rounded-full" :class="item.type === 'error' ? 'bg-white/80' : 'bg-gold'"></span>
            <span x-text="item.message"></span>
        </div>
    </template>
</div>
