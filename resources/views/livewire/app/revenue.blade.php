@php
    $percent = fn (?float $value): string => $value === null ? '–' : number_format($value * 100, 0, ',', '.').'%';
    $change = $comparison['change'];
@endphp

<div>
    <x-ui.page-header :title="$isOwner ? 'Omzet' : 'Mijn omzet'" :back="route('app.more')" />

    <div class="space-y-6 px-4 py-4">
        <x-ui.segmented label="Periode" wire:model.live="type" :value="$type" name="type"
            :options="['day' => 'Dag', 'week' => 'Week', 'month' => 'Maand', 'year' => 'Jaar', 'custom' => 'Anders']" />

        @if ($type === 'custom')
            <div class="grid grid-cols-2 gap-3">
                <x-ui.input label="Van" type="date" wire:model.live="anchor" />
                <x-ui.input label="Tot en met" type="date" wire:model.live="customTo" />
            </div>
        @else
            <div class="flex items-center justify-between gap-2">
                <x-ui.icon-button icon="chevron-left" label="Vorige periode" wire:click="shift(-1)" class="-ml-2" />
                <p class="text-base font-semibold">{{ $period->label() }}</p>
                <x-ui.icon-button icon="chevron-right" label="Volgende periode" wire:click="shift(1)" class="-mr-2" />
            </div>
        @endif

        {{-- Eén groot getal --}}
        <div wire:loading.class="opacity-50">
            <p class="text-xs font-medium text-muted">{{ $isOwner && ! $staffId ? 'Omzet van de zaak' : 'Omzet' }}</p>
            <p class="text-display font-bold tracking-tight tabular-nums">{{ money($comparison['revenue_cents']) }}</p>
            <p class="mt-1 flex items-center gap-1 text-xs text-muted">
                @if ($change !== null)
                    <span @class(['inline-flex items-center gap-0.5 font-semibold', 'text-completed' => $change >= 0, 'text-no-show' => $change < 0])>
                        <x-ui.icon :name="$change >= 0 ? 'arrow-up' : 'arrow-down'" size="12" />
                        {{ ($change >= 0 ? '+' : '').number_format($change * 100, 0, ',', '.') }}%
                    </span>
                @endif
                <span>{{ $change === null ? 'Geen omzet in de periode ervoor' : $period->comparisonLabel() }}</span>
            </p>
            @if ($kpis['tips_cents'] > 0)
                <p class="mt-1 text-xs text-muted">Daarnaast {{ money($kpis['tips_cents']) }} fooi</p>
            @endif
        </div>

        {{-- Staafgrafiek --}}
        <div class="rounded-card border border-border p-3" role="img" aria-label="Omzet per {{ $type === 'day' ? 'uur' : ($type === 'year' ? 'maand' : 'dag') }}">
            <div class="flex h-32 items-end gap-px">
                @foreach ($series as $bar)
                    <div class="flex h-full min-w-0 flex-1 flex-col justify-end" title="{{ $bar['label'] }}: {{ money($bar['cents']) }}">
                        <div class="rounded-t-[3px] {{ $bar['cents'] > 0 ? 'bg-accent' : 'bg-border' }}" style="height: {{ $bar['cents'] > 0 ? max(4, round($bar['cents'] / $max * 100)) : 2 }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex gap-px text-center text-[0.625rem] leading-4 text-muted" aria-hidden="true">
                @foreach ($series as $index => $bar)
                    <span class="min-w-0 flex-1 truncate">{{ count($series) <= 15 || $index % 5 === 0 ? $bar['label'] : '' }}</span>
                @endforeach
            </div>
        </div>

        {{-- KPI-tegels --}}
        <div class="grid grid-cols-2 gap-3">
            <x-ui.stat label="Afspraken" :value="$kpis['appointments']" />
            <x-ui.stat label="Gemiddeld" :value="money($kpis['average_cents'])" />
            <x-ui.stat label="Bezetting" :value="$percent($kpis['occupancy'])" />
            <x-ui.stat label="No-show" :value="$percent($kpis['no_show_rate'])" :hint="$kpis['no_shows'] > 0 ? $kpis['no_shows'].' keer' : null" />
            <x-ui.stat label="Nieuwe klanten" :value="$kpis['new_customers']" />
            <x-ui.stat label="Terugkeer" :value="$percent($kpis['return_rate'])" hint="laatste 90 dagen" />
        </div>

        @if ($kpis['by_payment_method'] !== [])
            <x-ui.section title="Per betaalmethode">
                <x-ui.card :padding="false" flat class="divide-y divide-border">
                    @foreach ($kpis['by_payment_method'] as $method => $cents)
                        <x-ui.list-item :title="\App\Services\ShopStatsService::paymentMethodLabel($method)" :chevron="false" wire:key="method-{{ $method }}">
                            <x-slot:trailing><span class="font-semibold tabular-nums">{{ money($cents) }}</span></x-slot:trailing>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.card>
            </x-ui.section>
        @endif

        @if ($isOwner && count($perStaff) > 1)
            <x-ui.section title="Per kapper" text="Tik op een kapper om alleen die cijfers te zien.">
                <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                    @if ($staffId)
                        <x-ui.list-item wire:click="$set('staffId', null)" title="Alle kappers" :chevron="false" />
                    @endif
                    @foreach ($perStaff as $row)
                        <x-ui.list-item wire:click="$set('staffId', {{ $row['staff']->id }})" wire:key="staff-{{ $row['staff']->id }}" :chevron="false"
                            :title="$row['staff']->name" :subtitle="$row['appointments'].' afspraken'.($row['tips_cents'] > 0 ? ' · '.money($row['tips_cents']).' fooi' : '')"
                            @class(['bg-surface' => $staffId === $row['staff']->id])>
                            <x-slot:leading><x-ui.avatar :name="$row['staff']->name" :color="$row['staff']->color" size="sm" /></x-slot:leading>
                            <x-slot:trailing><span class="font-semibold tabular-nums">{{ money($row['revenue_cents']) }}</span></x-slot:trailing>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.card>
            </x-ui.section>
        @endif

        <x-ui.button variant="secondary" full :href="route('app.revenue.export', ['maand' => $exportMonth])" download>
            Exporteer CSV · {{ nl_date($period->from, 'MMMM YYYY') }}
        </x-ui.button>
    </div>
</div>
