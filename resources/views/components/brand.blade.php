{{--
    Merk (B3): woordmerk met een kleine monogramtegel en een gouden punt. Eén plek om te vervangen zodra er een logo is.
    mark: toon de tegel · inverse: witte tekst voor donkere achtergronden.
--}}
@props(['href' => null, 'mark' => true, 'inverse' => false, 'size' => 'md'])

@php
    $tag = $href ? 'a' : 'span';
    $tile = $size === 'sm' ? 'size-6 text-xs' : 'size-8 text-sm';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif aria-label="BarberCuts"
    {{ $attributes->class(['group inline-flex min-h-11 items-center gap-2.5 font-semibold tracking-tight', $inverse ? 'text-white' : 'text-text', $size === 'sm' ? 'text-base' : 'text-lg']) }}>
    @if ($mark)
        <span class="relative flex shrink-0 items-center justify-center rounded-[0.55rem] font-bold {{ $tile }} {{ $inverse ? 'bg-white text-ink' : 'bg-ink text-white' }} transition-transform duration-200 ease-out-soft group-hover:-rotate-3">
            b<span class="absolute right-[0.2rem] bottom-[0.2rem] size-1 rounded-full bg-gold"></span>
        </span>
    @endif
    <span>barbercuts</span>
</{{ $tag }}>
