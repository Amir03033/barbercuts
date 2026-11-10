{{--
    Bottom navigation van de kapper-app: Vandaag · Agenda · + · Klanten · Meer.
    active: today | calendar | customers | more. Routes die nog niet bestaan worden uitgeschakeld getoond.
--}}
@props(['active' => null])

@php
    $link = fn (string $route): ?string => \Illuminate\Support\Facades\Route::has($route) ? route($route) : null;

    $items = [
        ['key' => 'today', 'label' => 'Vandaag', 'icon' => 'house', 'href' => $link('app.today')],
        ['key' => 'calendar', 'label' => 'Agenda', 'icon' => 'calendar', 'href' => $link('app.calendar')],
        ['key' => 'new', 'label' => 'Nieuwe afspraak', 'icon' => 'plus', 'href' => $link('app.appointments.create')],
        ['key' => 'customers', 'label' => 'Klanten', 'icon' => 'users', 'href' => $link('app.customers')],
        ['key' => 'more', 'label' => 'Meer', 'icon' => 'menu', 'href' => $link('app.more')],
    ];
@endphp

<nav aria-label="Hoofdmenu" {{ $attributes->class('fixed inset-x-0 bottom-0 z-40 border-t border-border bg-bg/95 pb-safe backdrop-blur') }}>
    <ul class="mx-auto grid h-nav max-w-2xl grid-cols-5 items-center">
        @foreach ($items as $item)
            @php($tag = $item['href'] ? 'a' : 'span')
            <li class="flex justify-center">
                @if ($item['key'] === 'new')
                    <{{ $tag }} @if ($item['href']) href="{{ $item['href'] }}" wire:navigate @else aria-disabled="true" @endif
                        aria-label="{{ $item['label'] }}"
                        class="flex size-12 items-center justify-center rounded-pill bg-accent text-on-accent shadow-soft active:opacity-80">
                        <x-ui.icon name="plus" />
                    </{{ $tag }}>
                @else
                    <{{ $tag }} @if ($item['href']) href="{{ $item['href'] }}" wire:navigate @else aria-disabled="true" @endif
                        @if ($active === $item['key']) aria-current="page" @endif
                        @class([
                            'flex min-h-11 min-w-14 flex-col items-center justify-center gap-0.5 rounded-pill px-2 text-xs font-medium',
                            'text-text' => $active === $item['key'],
                            'text-muted' => $active !== $item['key'],
                            'opacity-50' => ! $item['href'],
                        ])>
                        <x-ui.icon :name="$item['icon']" size="22" />
                        <span>{{ $item['label'] }}</span>
                    </{{ $tag }}>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
