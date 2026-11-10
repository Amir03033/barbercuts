<div>
    <x-ui.page-header title="Agenda" :subtitle="ucfirst(nl_date($day, 'dddd D MMMM'))">
        @unless ($isToday)
            <x-ui.button variant="ghost" size="sm" wire:click="goToToday">Vandaag</x-ui.button>
        @endunless
    </x-ui.page-header>

    <div class="space-y-3 px-4 pt-3">
        {{-- Weekstrip --}}
        <div class="flex items-center gap-1">
            <x-ui.icon-button icon="chevron-left" label="Vorige week" wire:click="shiftWeek(-1)" class="-ml-2" />
            <div class="min-w-0 flex-1">
                <x-ui.date-strip :days="$weekDays" wire:model.live="date" :selected="$date" class="!mx-0 justify-between !gap-1 !px-0" compact />
            </div>
            <x-ui.icon-button icon="chevron-right" label="Volgende week" wire:click="shiftWeek(1)" class="-mr-2" />
        </div>

        {{-- Kapperkeuze --}}
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

    {{-- Tijdlijn: uren links, per kapper een kolom. Bij "Iedereen" scrollen de kolommen horizontaal binnen dit blok. --}}
    <div class="mt-3 flex border-t border-border" wire:loading.class="opacity-60" wire:target="date, staffFilter, shiftWeek, goToToday">
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

                    <div class="relative" style="height: {{ $height }}px"
                        x-on:click.self="$wire.newAt({{ $column['staff']->id }}, {{ $windowStart }} + Math.floor(($event.offsetY / {{ $scale }}) / {{ $interval }}) * {{ $interval }})"
                        role="group" aria-label="Agenda van {{ $column['staff']->name }}">
                        {{-- Uurlijnen --}}
                        @foreach ($hours as $index => $hour)
                            <div class="pointer-events-none absolute inset-x-0 border-t border-border/70" style="top: {{ $index * 60 * $scale }}px"></div>
                        @endforeach

                        {{-- Pauzes, vrij en buiten werktijd: grijs gearceerd --}}
                        @foreach ($column['off'] as $off)
                            <div class="pointer-events-none absolute inset-x-0 bg-surface"
                                style="top: {{ $off['top'] }}px; height: {{ $off['height'] }}px; background-image: repeating-linear-gradient(135deg, transparent 0 6px, var(--color-border) 6px 7px)"></div>
                        @endforeach

                        @if ($nowTop !== null)
                            <div class="pointer-events-none absolute inset-x-0 z-10 border-t-2 border-danger" style="top: {{ $nowTop }}px"></div>
                        @endif

                        @foreach ($column['appointments'] as $item)
                            @php($appointment = $item['model'])
                            <button type="button" data-timeline-item wire:click="selectAppointment({{ $appointment->id }})" wire:key="appointment-{{ $appointment->id }}"
                                class="absolute inset-x-1 overflow-hidden rounded-[0.5rem] border-l-4 px-2 text-left text-xs leading-4 text-text {{ $appointment->status->value === 'no_show' ? 'opacity-50' : '' }}"
                                style="top: {{ $item['top'] + 1 }}px; height: {{ $item['height'] }}px; border-color: {{ $column['staff']->color }}; background-color: color-mix(in srgb, {{ $column['staff']->color }} 14%, var(--color-bg))">
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
                <x-ui.empty-state icon="users" title="Geen actieve kappers" text="Voeg een kapper toe bij Meer → Kappers." class="flex-1" />
            @endif
        </div>
    </div>

    <p class="px-4 py-3 text-xs text-muted">Tik op een lege plek om een afspraak te maken.</p>

    <x-app.appointment-sheet :appointment="$selected" :can-update="$canUpdateSelected" :mode="$sheetMode" :timezone="$shop->timezone"
        :custom-tip="$customTip" :tip="$tip" />
</div>
