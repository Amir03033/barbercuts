{{--
    Vloeiende lijngrafiek met vlak eronder. Pure SVG: de lijn "tekent" zichzelf bij het laden en bij een nieuwe periode.
    series: lijst van ['label' => '..', 'value' => int] · format: money | int · height: kijkhoogte.
--}}
@props(['series' => [], 'format' => 'money', 'height' => 180])

@php
    $width = 600;
    $padTop = 14;
    $padBottom = 10;
    $count = count($series);
    $max = max(1, ...array_map(fn (array $point): int => (int) $point['value'], $series ?: [['value' => 1]]));
    $x = fn (int $index): float => $count <= 1 ? $width / 2 : $index / ($count - 1) * $width;
    $y = fn (int $value): float => $padTop + (1 - $value / $max) * ($height - $padTop - $padBottom);
    $points = [];

    foreach ($series as $index => $point) {
        $points[] = [$x($index), $y((int) $point['value'])];
    }

    // Vloeiende lijn: kubische Bézier-bochten met horizontale raaklijnen (geen overshoot)
    $path = '';

    foreach ($points as $index => [$px, $py]) {
        if ($index === 0) {
            $path = sprintf('M%.1f %.1f', $px, $py);

            continue;
        }

        [$prevX, $prevY] = $points[$index - 1];
        $mid = ($px + $prevX) / 2;
        $path .= sprintf(' C%.1f %.1f %.1f %.1f %.1f %.1f', $mid, $prevY, $mid, $py, $px, $py);
    }

    $area = $points === [] ? '' : $path.sprintf(' L%.1f %.1f L%.1f %.1f Z', $points[$count - 1][0], $height, $points[0][0], $height);
    $id = 'ac'.substr(md5(json_encode($series).$height), 0, 8);
    $fmt = fn (int $value): string => $format === 'money' ? money($value) : number_format($value, 0, ',', '.');
@endphp

<div {{ $attributes->class('w-full') }}>
    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="block h-auto w-full overflow-visible" role="img" aria-label="Grafiek met {{ $count }} punten, hoogste waarde {{ $fmt($max) }}">
        <defs>
            <linearGradient id="{{ $id }}" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stop-color="var(--bc-gold)" stop-opacity="0.28" />
                <stop offset="100%" stop-color="var(--bc-gold)" stop-opacity="0" />
            </linearGradient>
        </defs>

        @foreach ([0.25, 0.5, 0.75] as $line)
            <line x1="0" x2="{{ $width }}" y1="{{ $padTop + $line * ($height - $padTop - $padBottom) }}" y2="{{ $padTop + $line * ($height - $padTop - $padBottom) }}" stroke="var(--bc-border)" stroke-dasharray="3 5" />
        @endforeach

        @if ($points !== [])
            <path d="{{ $area }}" fill="url(#{{ $id }})" class="animate-fade-in" style="animation-delay: 250ms" />
            <path d="{{ $path }}" fill="none" stroke="var(--bc-gold)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" pathLength="1"
                class="animate-draw" style="stroke-dasharray: 1; --len: 1" />

            @foreach ($series as $index => $point)
                <g class="group">
                    <circle cx="{{ $points[$index][0] }}" cy="{{ $points[$index][1] }}" r="14" fill="transparent"><title>{{ $point['label'] }}: {{ $fmt((int) $point['value']) }}</title></circle>
                    <circle cx="{{ $points[$index][0] }}" cy="{{ $points[$index][1] }}" r="4.5" fill="var(--bc-card)" stroke="var(--bc-gold)" stroke-width="2.5"
                        class="pointer-events-none opacity-0 transition-opacity duration-150 group-hover:opacity-100" />
                </g>
            @endforeach
        @endif
    </svg>

    <div class="mt-2 flex justify-between text-[0.6875rem] text-muted" aria-hidden="true">
        @foreach ($series as $index => $point)
            <span @class(['min-w-0 truncate text-center', 'hidden sm:block' => $count > 8 && $index % 2 === 1, 'invisible' => $count > 14 && $index % 5 !== 0])>{{ $point['label'] }}</span>
        @endforeach
    </div>
</div>
