{{--
    Zijbalk voor desktop (lg en breder): donkere houtskool met een gouden accent op het actieve item.
    Inklapbaar; de staat wordt onthouden. Op mobiel en tablet neemt de bottom navigation dit over.
    Verwacht `collapsed` uit een omliggende Alpine-scope (zie layouts/app).
--}}
@props(['shop', 'isOwner' => false])

@php
    $user = auth()->user();
    $groups = [
        'Overzicht' => array_values(array_filter([
            ['route' => 'app.today', 'match' => ['app.today'], 'label' => 'Overzicht', 'icon' => 'layout-dashboard'],
            ['route' => 'app.calendar', 'match' => ['app.calendar', 'app.appointments.*'], 'label' => 'Agenda', 'icon' => 'calendar'],
            ['route' => 'app.customers', 'match' => ['app.customers', 'app.customers.*'], 'label' => 'Klanten', 'icon' => 'users'],
            ['route' => 'app.revenue', 'match' => ['app.revenue'], 'label' => $isOwner ? 'Omzet' : 'Mijn omzet', 'icon' => 'trending-up'],
            ['route' => 'app.reviews', 'match' => ['app.reviews'], 'label' => 'Reviews', 'icon' => 'star'],
            ['route' => 'app.waitlist', 'match' => ['app.waitlist'], 'label' => 'Wachtlijst', 'icon' => 'clock'],
            $isOwner ? ['route' => 'app.winback', 'match' => ['app.winback'], 'label' => 'Klanten terughalen', 'icon' => 'users'] : null,
            $isOwner ? ['route' => 'app.loyalty', 'match' => ['app.loyalty'], 'label' => 'Spaarkaart', 'icon' => 'star'] : null,
        ])),
        'Beheer' => array_values(array_filter([
            ['route' => 'app.staff', 'match' => ['app.staff', 'app.staff.*'], 'label' => 'Kappers', 'icon' => 'user'],
            $isOwner ? ['route' => 'app.services', 'match' => ['app.services', 'app.services.*'], 'label' => 'Diensten', 'icon' => 'scissors'] : null,
            $isOwner ? ['route' => 'app.opening-hours', 'match' => ['app.opening-hours'], 'label' => 'Openingstijden', 'icon' => 'clock'] : null,
            $isOwner ? ['route' => 'app.appearance', 'match' => ['app.appearance'], 'label' => 'Uiterlijk', 'icon' => 'palette'] : null,
            $isOwner ? ['route' => 'app.settings', 'match' => ['app.settings'], 'label' => 'Instellingen', 'icon' => 'settings'] : null,
            $isOwner && \App\Support\Features::billing() && ! $shop->is_demo ? ['route' => 'app.subscription', 'match' => ['app.subscription'], 'label' => 'Abonnement', 'icon' => 'credit-card'] : null,
        ])),
    ];
@endphp

<aside aria-label="Navigatie" :class="collapsed ? 'w-[4.5rem]' : 'w-60'"
    class="fixed inset-y-0 left-0 z-30 hidden flex-col bg-charcoal text-[#a9a7a2] transition-[width] duration-300 ease-out-soft lg:flex">
    {{-- Merk en inklappen --}}
    <div class="flex h-16 shrink-0 items-center justify-between px-4">
        <x-brand :href="route('app.today')" inverse size="sm" x-show="! collapsed" class="!min-h-0" />
        <span x-show="collapsed" x-cloak class="mx-auto"><x-brand :href="route('app.today')" inverse size="sm" :mark="true" class="!min-h-0 [&>span:last-child]:hidden" /></span>
        <button type="button" x-on:click="collapsed = ! collapsed" x-show="! collapsed" :aria-label="collapsed ? 'Zijbalk uitklappen' : 'Zijbalk inklappen'"
            class="flex size-8 items-center justify-center rounded-lg text-[#8d8b86] transition-colors duration-150 hover:bg-charcoal-2 hover:text-white">
            <x-ui.icon name="panel-left" size="18" />
        </button>
    </div>

    <div class="px-3 pb-2">
        <a href="{{ route('app.appointments.create') }}" wire:navigate title="Nieuwe afspraak"
            class="flex min-h-10 items-center justify-center gap-2 rounded-input bg-gold text-sm font-semibold text-ink shadow-[inset_0_1px_0_rgb(255_255_255/0.3)] transition-[filter,transform] duration-150 ease-out-soft hover:brightness-105 active:scale-[0.98]">
            <x-ui.icon name="plus" size="18" />
            <span x-show="! collapsed" x-transition.opacity.duration.200ms>Nieuwe afspraak</span>
        </a>
    </div>

    {{-- Navigatie --}}
    <nav class="no-scrollbar flex-1 space-y-5 overflow-y-auto px-3 py-3">
        @foreach ($groups as $group => $items)
            <div>
                <p x-show="! collapsed" x-transition.opacity.duration.200ms class="px-3 pb-1.5 text-[0.6875rem] font-semibold tracking-[0.08em] text-[#6f6d69] uppercase">{{ $group }}</p>
                <div x-show="collapsed" x-cloak class="mx-3 mb-2 h-px bg-white/10"></div>
                <ul class="space-y-0.5">
                    @foreach ($items as $item)
                        @php($isActive = request()->routeIs(...$item['match']))
                        <li>
                            <a href="{{ route($item['route']) }}" wire:navigate title="{{ $item['label'] }}" @if ($isActive) aria-current="page" @endif
                                @class([
                                    'group relative flex min-h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-[background-color,color] duration-150',
                                    'bg-charcoal-2 text-white' => $isActive,
                                    'hover:bg-charcoal-2/70 hover:text-white' => ! $isActive,
                                ])>
                                @if ($isActive)
                                    <span class="animate-pop-in absolute top-2 bottom-2 -left-3 w-[3px] rounded-r-full bg-gold"></span>
                                @endif
                                <x-ui.icon :name="$item['icon']" size="20" @class(['shrink-0 transition-colors duration-150', 'text-gold' => $isActive, 'group-hover:text-white' => ! $isActive]) />
                                <span x-show="! collapsed" x-transition.opacity.duration.200ms class="truncate">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    {{-- Zaak en gebruiker --}}
    <div class="shrink-0 space-y-2 border-t border-white/10 p-3">
        <button type="button" x-show="collapsed" x-cloak x-on:click="collapsed = false" aria-label="Zijbalk uitklappen"
            class="flex min-h-10 w-full items-center justify-center rounded-lg text-[#8d8b86] transition-colors duration-150 hover:bg-charcoal-2 hover:text-white">
            <x-ui.icon name="panel-left" size="18" />
        </button>

        <a href="{{ $shop->bookingUrl() }}" target="_blank" rel="noopener" title="Boekingspagina openen"
            class="group flex min-h-10 items-center gap-3 rounded-lg px-3 text-sm transition-colors duration-150 hover:bg-charcoal-2/70 hover:text-white">
            <x-ui.icon name="eye" size="20" class="shrink-0" />
            <span x-show="! collapsed" x-transition.opacity.duration.200ms class="min-w-0 flex-1 truncate">Mijn boekingspagina</span>
            <x-ui.icon name="arrow-up-right" size="14" x-show="! collapsed" class="shrink-0 opacity-0 transition-opacity duration-150 group-hover:opacity-100" />
        </a>

        <x-ui.dropdown align="left" placement="top" width="w-56" label="Account">
            <x-slot:trigger>
                <button type="button" class="flex w-full items-center gap-3 rounded-lg p-2 text-left transition-colors duration-150 hover:bg-charcoal-2/70">
                    <x-ui.avatar :name="$user->name" size="sm" />
                    <span x-show="! collapsed" x-transition.opacity.duration.200ms class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-white">{{ $user->name }}</span>
                        <span class="block truncate text-xs text-[#8d8b86]">{{ $isOwner ? 'Eigenaar' : 'Kapper' }} · {{ $shop->name }}</span>
                    </span>
                    <x-ui.icon name="chevron-up" size="16" x-show="! collapsed" class="shrink-0" />
                </button>
            </x-slot:trigger>

            <div>
                <x-ui.dropdown-item :href="route('app.profile')" icon="user">Mijn profiel</x-ui.dropdown-item>
                <x-ui.dropdown-item :href="route('app.more')" icon="menu">Alle onderdelen</x-ui.dropdown-item>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-ui.dropdown-item type="submit" icon="log-out" danger>Uitloggen</x-ui.dropdown-item>
                </form>
            </div>
        </x-ui.dropdown>
    </div>
</aside>
