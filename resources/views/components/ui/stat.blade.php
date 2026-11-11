{{-- KPI-tegel. delta: bv. "+12%"; trend: up | down | null bepaalt de kleur. icon: klein icoon links van het label. --}}
@props(['label', 'value', 'delta' => null, 'trend' => null, 'hint' => null, 'icon' => null])

<div {{ $attributes->class('rounded-card border border-border bg-card p-4 shadow-soft lg:p-5') }}>
    <div class="flex items-center gap-2 text-xs font-medium text-muted">
        @if ($icon)
            <span class="flex size-6 items-center justify-center rounded-full bg-gold-soft text-gold-ink"><x-ui.icon :name="$icon" size="14" /></span>
        @endif
        <span>{{ $label }}</span>
    </div>
    <p class="tnum mt-2 text-[1.625rem] leading-8 font-semibold tracking-tight whitespace-nowrap text-text lg:text-[1.75rem]">{{ $value }}</p>
    @if ($delta || $hint)
        <p class="mt-1.5 flex flex-wrap items-center gap-x-1.5 text-xs text-muted">
            @if ($delta)
                <span @class(['inline-flex items-center gap-0.5 rounded-pill px-1.5 py-0.5 font-semibold', 'bg-completed/10 text-completed' => $trend === 'up', 'bg-no-show/10 text-no-show' => $trend === 'down', 'bg-surface' => ! $trend])>
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
