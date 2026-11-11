{{-- Verdeling in een gestapelde balk met legenda. items: lijst van ['label' => '', 'value' => int, 'color' => '#hex']. --}}
@props(['items' => []])

@php($total = max(1, array_sum(array_map(fn (array $item): int => (int) $item['value'], $items))))

<div {{ $attributes }}>
    <div class="flex h-2.5 overflow-hidden rounded-pill bg-surface" role="img" aria-label="Verdeling van afspraken">
        @foreach ($items as $item)
            @if ($item['value'] > 0)
                <span class="h-full transition-[width] duration-700 ease-out-soft first:rounded-l-pill last:rounded-r-pill" style="width: {{ $item['value'] / $total * 100 }}%; background-color: {{ $item['color'] }}" title="{{ $item['label'] }}: {{ $item['value'] }}"></span>
            @endif
        @endforeach
    </div>
    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3">
        @foreach ($items as $item)
            <div class="flex items-center gap-2 text-sm">
                <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                <dt class="text-muted">{{ $item['label'] }}</dt>
                <dd class="tnum ml-auto font-semibold">{{ $item['value'] }}</dd>
            </div>
        @endforeach
    </dl>
</div>
