{{--
    Voortgang van een persoonlijke demo voor de pitcher: vijf stappen (geopend → gestart → rondgekeken → aanmelden
    begonnen → klant geworden) en de cijfers: hoe vaak geopend en gestart, hoe lang, wat bekeken, afspraken gemaakt.
--}}
@props(['stats'])

@php
    $steps = ['Geopend', 'Gestart', 'Bekeken', 'Begonnen', 'Klant'];
    $hint = match ($stats['step']) {
        0 => 'Nog niet geopend. Stuur de link nog eens of bel even.',
        1 => 'De link is geopend, maar de demo nog niet gestart.',
        2 => 'De demo is gestart. Vraag wat hij ervan vond.',
        3 => 'Er is goed rondgekeken. Een mooi moment om te bellen.',
        4 => 'Aanmelden is begonnen maar nog niet afgerond. Help even mee?',
        default => 'Aangemeld. Goed gedaan!',
    };
@endphp

<div {{ $attributes }}>
    {{-- Stappen --}}
    <ol class="flex items-center gap-1" aria-label="Voortgang">
        @foreach ($steps as $index => $label)
            @php($done = $stats['step'] > $index)
            <li class="flex min-w-0 flex-1 flex-col gap-1">
                <span @class(['h-1.5 rounded-full', 'bg-completed' => $done && $stats['step'] === 5, 'bg-link' => $done && $stats['step'] < 5, 'bg-border' => ! $done])></span>
                <span @class(['truncate text-[0.625rem] font-medium', 'text-text' => $done, 'text-muted' => ! $done])>{{ $label }}</span>
            </li>
        @endforeach
    </ol>
    <p class="mt-2 text-xs text-muted">{{ $hint }}</p>

    {{-- Cijfers --}}
    <dl class="mt-3 grid grid-cols-4 gap-2 text-center">
        @foreach ([['Geopend', $stats['views'].'×'], ['Gestart', $stats['starts'].'×'], ['Kijktijd', $stats['minutes'] > 0 ? $stats['minutes'].' min' : '–'], ['Afspraken', $stats['appointments']]] as [$label, $value])
            <div class="rounded-xl bg-card px-1 py-2 ring-1 ring-border/50">
                <dt class="text-[0.625rem] font-medium text-muted">{{ $label }}</dt>
                <dd class="text-sm font-bold tabular-nums">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($stats['explored'] !== [])
        <div class="mt-3">
            <p class="text-[0.6875rem] font-medium text-muted">Bekeken{{ $stats['last_active'] ? ' · laatst actief '.$stats['last_active']->diffForHumans() : '' }}</p>
            <ul class="mt-1.5 flex flex-wrap gap-1.5">
                @foreach ($stats['explored'] as $section)
                    <li class="rounded-pill bg-card px-2.5 py-1 text-[0.6875rem] font-medium ring-1 ring-border/60">{{ $section }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
