<div>
    <x-ui.page-header title="Agenda" :subtitle="$periodLabel">
        <x-ui.button :href="route('app.appointments.create')" icon="plus" size="sm" class="max-lg:hidden!" wire:navigate>Nieuwe afspraak</x-ui.button>
        @unless ($isToday && $view === 'day')
            <x-ui.button variant="secondary" size="sm" wire:click="goToToday">Vandaag</x-ui.button>
        @endunless
    </x-ui.page-header>

    <div class="space-y-3 px-4 pt-3 lg:pt-1">
        {{-- Weergave, navigatie en kapperkeuze --}}
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.segmented label="Weergave" wire:model.live="view" :value="$view" name="view" class="w-full sm:w-72"
                :options="['day' => 'Dag', 'week' => 'Week', 'month' => 'Maand']" />
            <div class="flex items-center gap-1 max-sm:w-full max-sm:justify-between">
                <x-ui.icon-button icon="chevron-left" label="Vorige" wire:click="shift(-1)" />
                <p class="min-w-40 text-center text-sm font-semibold tabular-nums">{{ $periodLabel }}</p>
                <x-ui.icon-button icon="chevron-right" label="Volgende" wire:click="shift(1)" />
            </div>
        </div>

        @if ($view === 'day')
            <div class="flex items-center gap-1">
                <x-ui.icon-button icon="chevron-left" label="Vorige week" wire:click="shiftWeek(-1)" class="-ml-2" />
                <div class="min-w-0 flex-1">
                    <x-ui.date-strip :days="$weekDays" wire:model.live="date" :selected="$date" class="!mx-0 justify-between !gap-1 !px-0" compact />
                </div>
                <x-ui.icon-button icon="chevron-right" label="Volgende week" wire:click="shiftWeek(1)" class="-mr-2" />
            </div>
        @endif

        @if ($staff->count() > 1)
            <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4">
                <x-ui.chip wire:model.live="staffFilter" value="all" :checked="$staffFilter === 'all'">Iedereen</x-ui.chip>
                @foreach ($staff as $member)
                    <x-ui.chip wire:model.live="staffFilter" :value="$member->id" :checked="$staffFilter === (string) $member->id" wire:key="filter-{{ $member->id }}">
                        <span class="size-2.5 rounded-full" style="background-color: {{ $member->color }}" aria-hidden="true"></span>
                        {{ $member->name }}
                    </x-ui.chip>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-3 transition-opacity duration-200" wire:loading.class="opacity-60" wire:target="date, staffFilter, shift, shiftWeek, goToToday, view, selectDay">
        {{-- ───────── Dag ───────── --}}
        @if ($view === 'day')
            <div class="animate-fade-up flex border-t border-border bg-card lg:mx-0 lg:rounded-card lg:border lg:shadow-soft" wire:key="day-{{ $date }}-{{ $staffFilter }}">
                <div class="w-11 shrink-0 @if (count($columns) > 1) pt-9 @endif" aria-hidden="true">
                    <div class="relative" style="height: {{ $height }}px">
                        @foreach ($hours as $index => $hour)
                            <span class="absolute right-1.5 -translate-y-1/2 text-xs text-muted tabular-nums" style="top: {{ $index * 60 * $scale }}px">
                                {{ $index === 0 ? '' : sprintf('%02d:00', $hour) }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <div class="no-scrollbar flex min-w-0 flex-1 overflow-x-auto">
                    @foreach ($columns as $column)
                        <div class="min-w-36 flex-1 border-l border-border" wire:key="column-{{ $column['staff']->id }}">
                            @if (count($columns) > 1)
                                <p class="flex h-9 items-center justify-center gap-1.5 border-b border-border text-xs font-semibold">
                                    <span class="size-2 rounded-full" style="background-color: {{ $column['staff']->color }}" aria-hidden="true"></span>
                                    <span class="truncate">{{ $column['staff']->name }}</span>
                                </p>
                            @endif

                            <div class="group/col relative" style="height: {{ $height }}px"
                                x-on:click.self="$wire.newAt({{ $column['staff']->id }}, {{ $windowStart }} + Math.floor(($event.offsetY / {{ $scale }}) / {{ $interval }}) * {{ $interval }})"
                                role="group" aria-label="Agenda van {{ $column['staff']->name }}">
                                @foreach ($hours as $index => $hour)
                                    <div class="pointer-events-none absolute inset-x-0 border-t border-border/70" style="top: {{ $index * 60 * $scale }}px"></div>
                                @endforeach

                                @foreach ($column['off'] as $off)
                                    <div class="pointer-events-none absolute inset-x-0 bg-surface"
                                        style="top: {{ $off['top'] }}px; height: {{ $off['height'] }}px; background-image: repeating-linear-gradient(135deg, transparent 0 6px, var(--color-border) 6px 7px)"></div>
                                @endforeach

                                @if ($nowTop !== null)
                                    <div class="pointer-events-none absolute inset-x-0 z-10 border-t-2 border-danger" style="top: {{ $nowTop }}px">
                                        <span class="absolute -top-[5px] -left-1 size-2 rounded-full bg-danger"></span>
                                    </div>
                                @endif

                                @foreach ($column['appointments'] as $item)
                                    @php($appointment = $item['model'])
                                    <button type="button" data-timeline-item wire:click="selectAppointment({{ $appointment->id }})" wire:key="appointment-{{ $appointment->id }}"
                                        class="absolute inset-x-1 overflow-hidden rounded-[0.5rem] border-l-4 px-2 text-left text-xs leading-4 text-text transition-[transform,box-shadow] duration-200 ease-out-soft hover:z-20 hover:shadow-lift active:scale-[0.99] {{ $appointment->status->value === 'no_show' ? 'opacity-50' : '' }}"
                                        style="top: {{ $item['top'] + 1 }}px; height: {{ $item['height'] }}px; border-color: {{ $column['staff']->color }}; background-color: color-mix(in srgb, {{ $column['staff']->color }} 14%, var(--color-card))">
                                        <span class="block truncate font-semibold">
                                            {{ $item['time'] }} {{ $appointment->customer->name }}
                                            @if ($appointment->status->value === 'completed') ✓ @endif
                                        </span>
                                        @if ($item['height'] >= 38)
                                            <span class="block truncate text-muted">{{ $appointment->service_name }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    @if ($columns === [])
                        <x-ui.empty-state icon="users" title="Geen actieve kappers" text="Voeg een kapper toe bij Kappers." class="flex-1" />
                    @endif
                </div>
            </div>
            <p class="px-4 py-3 text-xs text-muted">Tik op een lege plek om een afspraak te maken.</p>

        {{-- ───────── Week ───────── --}}
        @elseif ($view === 'week')
            <div class="animate-fade-up border-t border-border bg-card lg:rounded-card lg:border lg:shadow-soft" wire:key="week-{{ $date }}-{{ $staffFilter }}">
                <div class="no-scrollbar overflow-x-auto">
                    <div class="grid min-w-[52rem] grid-cols-[2.75rem_repeat(7,minmax(0,1fr))]">
                        <div></div>
                        @foreach ($weekColumns as $column)
                            <button type="button" wire:click="selectDay('{{ $column['date'] }}')" wire:key="whead-{{ $column['date'] }}"
                                class="flex h-14 flex-col items-center justify-center border-b border-l border-border text-xs transition-colors duration-150 hover:bg-surface">
                                <span class="text-muted">{{ $column['label'] }}</span>
                                <span @class(['mt-0.5 flex size-7 items-center justify-center rounded-full text-sm font-semibold tabular-nums', 'bg-accent text-on-accent' => $column['isToday']])>{{ $column['number'] }}</span>
                            </button>
                        @endforeach

                        <div class="relative" style="height: {{ $height }}px" aria-hidden="true">
                            @foreach ($hours as $index => $hour)
                                <span class="absolute right-1.5 -translate-y-1/2 text-xs text-muted tabular-nums" style="top: {{ $index * 60 * $scale }}px">{{ $index === 0 ? '' : sprintf('%02d:00', $hour) }}</span>
                            @endforeach
                        </div>
                        @foreach ($weekColumns as $column)
                            <div class="relative border-l border-border {{ $column['isToday'] ? 'bg-gold-soft/30' : '' }}" style="height: {{ $height }}px" wire:key="wcol-{{ $column['date'] }}">
                                @foreach ($hours as $index => $hour)
                                    <div class="pointer-events-none absolute inset-x-0 border-t border-border/70" style="top: {{ $index * 60 * $scale }}px"></div>
                                @endforeach
                                @foreach ($column['appointments'] as $item)
                                    @php($appointment = $item['model'])
                                    <button type="button" data-timeline-item wire:click="selectAppointment({{ $appointment->id }})" wire:key="wappointment-{{ $appointment->id }}"
                                        class="absolute inset-x-0.5 overflow-hidden rounded-md border-l-[3px] px-1.5 text-left text-xs leading-4 text-text transition-[transform,box-shadow] duration-200 ease-out-soft hover:z-20 hover:shadow-lift {{ $appointment->status->value === 'no_show' ? 'opacity-50' : '' }}"
                                        style="top: {{ $item['top'] + 1 }}px; height: {{ $item['height'] }}px; border-color: {{ $appointment->staffMember->color }}; background-color: color-mix(in srgb, {{ $appointment->staffMember->color }} 14%, var(--color-card))">
                                        <span class="block truncate font-semibold">{{ $item['time'] }} {{ $appointment->customer->name }}</span>
                                        @if ($item['height'] >= 38)
                                            <span class="block truncate text-muted">{{ $appointment->service_name }}@if ($multiStaff) · {{ $appointment->staffMember->name }}@endif</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <p class="px-4 py-3 text-xs text-muted">Tik op een dag om de dagweergave te openen.</p>

        {{-- ───────── Maand ───────── --}}
        @else
            <div class="animate-fade-up px-4" wire:key="month-{{ $date }}-{{ $staffFilter }}">
                <div class="overflow-hidden rounded-card border border-border bg-card shadow-soft">
                    <div class="grid grid-cols-7 border-b border-border bg-surface/50 text-center text-xs font-medium text-muted">
                        @foreach (['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'] as $name)
                            <p class="py-2">{{ $name }}</p>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7 gap-px bg-border">
                        @foreach ($weeks as $cells)
                            @foreach ($cells as $cell)
                                <button type="button" wire:click="selectDay('{{ $cell['date'] }}')" wire:key="mcell-{{ $cell['date'] }}"
                                    aria-label="{{ nl_date(\Carbon\CarbonImmutable::parse($cell['date']), 'dddd D MMMM') }}, {{ $cell['count'] }} {{ $cell['count'] === 1 ? 'afspraak' : 'afspraken' }}"
                                    @class([
                                        'flex min-h-16 flex-col items-center gap-1 bg-card p-1.5 transition-colors duration-150 hover:bg-surface lg:min-h-28 lg:items-start lg:p-2.5',
                                        'opacity-45' => ! $cell['inMonth'],
                                    ])>
                                    <span @class(['flex size-7 items-center justify-center rounded-full text-sm font-medium tabular-nums', 'bg-accent text-on-accent' => $cell['isToday']])>{{ $cell['number'] }}</span>
                                    @if ($cell['count'] > 0)
                                        <span class="text-xs font-semibold tabular-nums lg:rounded-pill lg:bg-gold-soft lg:px-2 lg:py-0.5 lg:text-gold-ink">{{ $cell['count'] }}<span class="hidden lg:inline"> {{ $cell['count'] === 1 ? 'afspraak' : 'afspraken' }}</span></span>
                                        <span class="flex gap-0.5">
                                            @foreach ($cell['colors'] as $color)
                                                <span class="size-1.5 rounded-full" style="background-color: {{ $color }}"></span>
                                            @endforeach
                                        </span>
                                    @endif
                                </button>
                            @endforeach
                        @endforeach
                    </div>
                </div>
                <p class="py-3 text-xs text-muted">Tik op een dag om de afspraken te zien.</p>
            </div>
        @endif
    </div>

    <x-app.appointment-sheet :appointment="$selected" :can-update="$canUpdateSelected" :mode="$sheetMode" :timezone="$shop->timezone"
        :custom-tip="$customTip" :tip="$tip" :redeem="$redeem" />
</div>
