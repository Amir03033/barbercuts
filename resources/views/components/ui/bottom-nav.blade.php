{{--
    Bottom navigation (mobiel en tablet): Overzicht · Agenda · + · Klanten · Meer. Op desktop (lg) neemt de zijbalk dit over.
    active: today | calendar | customers | more.
--}}
@props(['active' => null])

@php
    $link = fn (string $route): ?string => \Illuminate\Support\Facades\Route::has($route) ? route($route) : null;

    $items = [
        ['key' => 'today', 'label' => 'Overzicht', 'icon' => 'layout-dashboard', 'href' => $link('app.today')],
        ['key' => 'calendar', 'label' => 'Agenda', 'icon' => 'calendar', 'href' => $link('app.calendar')],
        ['key' => 'new', 'label' => 'Nieuwe afspraak', 'icon' => 'plus', 'href' => $link('app.appointments.create')],
        ['key' => 'customers', 'label' => 'Klanten', 'icon' => 'users', 'href' => $link('app.customers')],
        ['key' => 'more', 'label' => 'Meer', 'icon' => 'menu', 'href' => $link('app.more')],
    ];
@endphp

<nav aria-label="Hoofdmenu" {{ $attributes->class('fixed inset-x-0 bottom-0 z-40 border-t border-border bg-card/90 pb-safe backdrop-blur-xl lg:hidden') }}>
    <ul class="mx-auto grid h-nav max-w-2xl grid-cols-5 items-center">
        @foreach ($items as $item)
            @php($tag = $item['href'] ? 'a' : 'span')
            <li class="flex justify-center">
                @if ($item['key'] === 'new')
                    <{{ $tag }} @if ($item['href']) href="{{ $item['href'] }}" wire:navigate @else aria-disabled="true" @endif
                        aria-label="{{ $item['label'] }}"
                        class="flex size-12 items-center justify-center rounded-pill bg-accent text-on-accent shadow-lift ring-4 ring-bg transition-transform duration-150 ease-out-soft active:scale-90">
                        <x-ui.icon name="plus" />
                    </{{ $tag }}>
                @else
                    <{{ $tag }} @if ($item['href']) href="{{ $item['href'] }}" wire:navigate @else aria-disabled="true" @endif
                        @if ($active === $item['key']) aria-current="page" @endif
                        @class([
                            'relative flex min-h-11 min-w-14 flex-col items-center justify-center gap-0.5 rounded-pill px-2 text-xs font-medium transition-colors duration-150',
                            'text-text' => $active === $item['key'],
                            'text-muted' => $active !== $item['key'],
                            'opacity-50' => ! $item['href'],
                        ])>
                        @if ($active === $item['key'])
                            <span class="animate-pop-in absolute -top-[0.35rem] h-0.5 w-6 rounded-full bg-gold"></span>
                        @endif
                        <x-ui.icon :name="$item['icon']" size="22" />
                        <span>{{ $item['label'] }}</span>
                    </{{ $tag }}>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
