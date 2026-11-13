{{--
    Merk (B3): woordmerk met een kleine monogramtegel. Eén plek om te vervangen zodra er een logo is.
    mark: toon de tegel · inverse: witte tekst voor donkere achtergronden.
--}}
@props(['href' => null, 'mark' => true, 'inverse' => false, 'size' => 'md'])

@php
    $tag = $href ? 'a' : 'span';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif aria-label="BarberCuts"
    {{ $attributes->class(['group inline-flex min-h-11 items-center gap-2.5 font-semibold tracking-tight', $inverse ? 'text-white' : 'text-text', $size === 'sm' ? 'text-base' : 'text-lg']) }}>
    @if ($mark)
        {{-- Hetzelfde beeldmerk als de iconen (App\Support\BrandMark): zwart met wit, of wit met zwart op een donkere achtergrond --}}
        <svg viewBox="0 0 100 100" aria-hidden="true" class="shrink-0 {{ $size === 'sm' ? 'size-6' : 'size-8' }}">
            <rect width="100" height="100" rx="22" fill="{{ $inverse ? '#ffffff' : '#000000' }}"/>
            <rect x="30" y="20" width="12" height="59" rx="6" fill="{{ $inverse ? '#000000' : '#ffffff' }}"/>
            <circle cx="50" cy="60" r="13" fill="none" stroke="{{ $inverse ? '#000000' : '#ffffff' }}" stroke-width="12"/>
        </svg>
    @endif
    <span>barbercuts</span>
</{{ $tag }}>
