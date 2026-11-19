{{-- Navigatie binnen het pitchergedeelte: overzicht, berichten, flyer en terug naar de demo. --}}
@props(['active' => 'overview'])

<nav aria-label="Pitcher" class="no-scrollbar -mx-4 mt-6 flex gap-2 overflow-x-auto px-4">
    @foreach (['overview' => ['Overzicht', 'pitcher.overview'], 'messages' => ['Berichten', 'pitcher.messages'], 'flyer' => ['Flyer', 'pitcher.flyer'], 'demo' => ['Demo', 'demo']] as $key => [$label, $route])
        @if ($key === 'flyer')
            {{-- De flyer is een losse printpagina: gewoon laden, niet via wire:navigate --}}
            <x-ui.chip :href="route($route)" :active="$active === $key">{{ $label }}</x-ui.chip>
        @else
            <x-ui.chip :href="route($route)" :active="$active === $key" wire:navigate>{{ $label }}</x-ui.chip>
        @endif
    @endforeach
</nav>
