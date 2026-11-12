{{-- De spaarkaart van een klant bij een zaak: stempels als rondjes en wat er te verdienen valt (fase 9). --}}
@props(['card', 'stamps' => 0, 'shopName' => null])

@php
    $shown = min($stamps, $card->stamps_required);
    $left = $card->stamps_required - $shown;
@endphp

<x-ui.card flat {{ $attributes }}>
    <div class="flex items-baseline justify-between gap-3">
        <p class="text-base font-semibold">{{ $card->name }}@if ($shopName) <span class="font-normal text-muted">· {{ $shopName }}</span>@endif</p>
        <p class="shrink-0 text-sm text-muted tabular-nums">{{ $stamps }} / {{ $card->stamps_required }}</p>
    </div>
    <div class="mt-3 flex flex-wrap gap-1.5" role="img" aria-label="{{ $stamps }} van {{ $card->stamps_required }} stempels">
        @foreach (range(1, $card->stamps_required) as $position)
            <span @class(['flex size-7 items-center justify-center rounded-full border text-xs', 'border-accent bg-accent text-on-accent' => $position <= $shown, 'border-border-strong text-muted' => $position > $shown])>
                @if ($position <= $shown)
                    <x-ui.icon name="check" size="14" />
                @endif
            </span>
        @endforeach
    </div>
    <p class="mt-3 text-sm text-muted">
        {{ $left === 0 ? 'Je kaart is vol: bij je volgende bezoek krijg je '.$card->rewardLabel().'.' : 'Nog '.$left.' '.($left === 1 ? 'stempel' : 'stempels').' voor '.$card->rewardLabel().'.' }}
    </p>
</x-ui.card>
