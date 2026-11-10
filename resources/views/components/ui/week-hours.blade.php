{{--
    Weekrooster met pauzes. Hoort bij de trait EditsWeekSchedule (property $week, acties addBlock/removeBlock/copyDayToAll).
    week: de huidige staat · errors: foutmeldingen per dag.
--}}
@props(['week', 'errors' => [], 'model' => 'week', 'copy' => true])

<div {{ $attributes->class('divide-y divide-border rounded-card border border-border') }}>
    @foreach (\App\Support\WeekSchedule::DAY_NAMES as $day => $dayName)
        @php($state = $week[$day] ?? ['open' => false, 'blocks' => []])
        <div class="p-4" wire:key="{{ $model }}-day-{{ $day }}">
            <x-ui.toggle :label="$dayName" :hint="$state['open'] ? null : 'Gesloten'" wire:model.live="{{ $model }}.{{ $day }}.open" :checked="$state['open']" />

            @if ($state['open'])
                <div class="mt-3 space-y-2">
                    @foreach ($state['blocks'] as $index => $block)
                        <div class="flex items-center gap-2" wire:key="{{ $model }}-day-{{ $day }}-block-{{ $index }}">
                            <input type="time" step="300" aria-label="{{ $dayName }} van" class="bc-input min-w-0 flex-1 tabular-nums"
                                wire:model.blur="{{ $model }}.{{ $day }}.blocks.{{ $index }}.from" />
                            <span class="text-muted" aria-hidden="true">–</span>
                            <input type="time" step="300" aria-label="{{ $dayName }} tot" class="bc-input min-w-0 flex-1 tabular-nums"
                                wire:model.blur="{{ $model }}.{{ $day }}.blocks.{{ $index }}.to" />
                            @if (count($state['blocks']) > 1)
                                <x-ui.icon-button icon="x" label="Blok verwijderen" wire:click="removeBlock({{ $day }}, {{ $index }})" />
                            @endif
                        </div>
                    @endforeach

                    @isset($errors[$day])
                        <p class="text-xs text-danger" role="alert">{{ $errors[$day] }}</p>
                    @endisset

                    <div class="flex flex-wrap gap-x-1">
                        <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="addBlock({{ $day }})">Pauze</x-ui.button>
                        @if ($copy)
                            <x-ui.button variant="ghost" size="sm" wire:click="copyDayToAll({{ $day }})">Kopieer naar alle dagen</x-ui.button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endforeach
</div>
