{{-- KPI-tegel. delta: bv. "+12%"; trend: up | down | null bepaalt de kleur. --}}
@props(['label', 'value', 'delta' => null, 'trend' => null, 'hint' => null])

<div {{ $attributes->class('rounded-card border border-border bg-bg p-4') }}>
    <p class="text-xs font-medium text-muted">{{ $label }}</p>
    <p class="mt-1 text-[1.625rem] leading-8 font-bold tracking-tight whitespace-nowrap text-text tabular-nums">{{ $value }}</p>
    @if ($delta || $hint)
        <p class="mt-1 flex items-center gap-1 text-xs text-muted">
            @if ($delta)
                <span @class(['inline-flex items-center gap-0.5 font-semibold', 'text-completed' => $trend === 'up', 'text-no-show' => $trend === 'down'])>
                    @if ($trend)
                        <x-ui.icon :name="$trend === 'up' ? 'arrow-up' : 'arrow-down'" size="12" />
                    @endif
                    {{ $delta }}
                </span>
            @endif
            @if ($hint)
                <span>{{ $hint }}</span>
            @endif
        </p>
    @endif
</div>
