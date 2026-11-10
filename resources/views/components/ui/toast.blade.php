{{--
    Toasts (3 sec). Eén keer in de layout plaatsen.
    Tonen: $dispatch('toast', 'Opgeslagen') · $this->dispatch('toast', message: 'Opgeslagen') · session()->flash('toast', '…').
    nav = true: boven de bottom nav.
--}}
@props(['nav' => false])

<div x-data="{
        items: [],
        add(detail) {
            const message = typeof detail === 'string' ? detail : (detail?.message ?? detail?.[0]?.message ?? '');
            if (! message) return;
            const id = Date.now() + Math.random();
            this.items.push({ id, message, type: detail?.type ?? 'default' });
            setTimeout(() => this.items = this.items.filter(item => item.id !== id), 3000);
        },
    }"
    x-init="@js(session('toast')) && add(@js(session('toast')))"
    x-on:toast.window="add($event.detail)"
    aria-live="polite" aria-atomic="true"
    class="pointer-events-none fixed inset-x-0 z-[60] flex flex-col items-center gap-2 px-4"
    style="bottom: calc({{ $nav ? 'var(--spacing-nav) + ' : '' }}env(safe-area-inset-bottom) + 1rem)">
    <template x-for="item in items" :key="item.id">
        <div x-transition role="status"
            class="pointer-events-auto max-w-sm rounded-card px-4 py-3 text-sm font-medium shadow-soft"
            :class="item.type === 'error' ? 'bg-danger text-white' : 'bg-primary text-white'"
            x-text="item.message"></div>
    </template>
</div>
