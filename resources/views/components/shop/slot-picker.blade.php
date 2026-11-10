{{-- Dag + tijd op één scherm. Hoort bij de trait PicksSlot (properties date/time, actie showMoreDays). --}}
{{-- Let op: de prop heet `times`, niet `slots` — die naam is in Livewire 4 gereserveerd. --}}
@props(['days', 'times', 'date', 'time', 'canLoadMore' => false])

<div {{ $attributes->class('space-y-5') }}>
    @if (collect($days)->every('disabled'))
        <x-ui.empty-state icon="calendar" title="Geen vrije tijden" text="In deze periode is alles vol of gesloten.">
            @if ($canLoadMore)
                <x-ui.button variant="secondary" wire:click="showMoreDays" loading="showMoreDays">Later zoeken</x-ui.button>
            @endif
        </x-ui.empty-state>
    @else
        <div>
            <x-ui.date-strip :days="$days" wire:model.live="date" :selected="$date" />
            @if ($canLoadMore)
                <div class="mt-1 flex justify-end">
                    <x-ui.button variant="ghost" size="sm" wire:click="showMoreDays" loading="showMoreDays">Meer dagen</x-ui.button>
                </div>
            @endif
        </div>

        <div wire:loading.class="opacity-50" wire:target="date">
            <p class="mb-3 text-base font-semibold">{{ $date ? ucfirst(nl_date(\Carbon\CarbonImmutable::parse($date), 'dddd D MMMM')) : '' }}</p>
            @if ($times === [])
                <p class="text-sm text-muted">Op deze dag zijn geen tijden meer vrij.</p>
            @else
                <x-ui.time-grid :times="$times" wire:model.live="time" :selected="$time" wire:key="times-{{ $date }}" />
            @endif
        </div>
    @endif
</div>
