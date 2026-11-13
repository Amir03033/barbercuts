{{--
    Telefoon voor de voorbeeldschermen op de landingspagina: zwarte rand, uitsparing bovenin en een statusbalk.
    Puur decoratief; de inhoud is fictief. label: omschrijving voor schermlezers (en onderschrift als caption = true). island: tekst van de melding in de uitsparing.
--}}
@props(['label', 'caption' => false, 'island' => null])

<figure {{ $attributes->class('mx-auto w-[17.5rem] shrink-0') }}>
    <div class="relative rounded-[3rem] bg-[#1d1d1f] p-[0.6rem] shadow-[0_40px_80px_-30px_rgb(0_0_0/0.45),0_0_0_1px_rgb(255_255_255/0.08)_inset]" role="img" aria-label="{{ $label }}">
        {{-- Zijknoppen --}}
        <span class="absolute top-28 -left-[3px] h-8 w-[3px] rounded-l bg-[#1d1d1f]" aria-hidden="true"></span>
        <span class="absolute top-40 -left-[3px] h-14 w-[3px] rounded-l bg-[#1d1d1f]" aria-hidden="true"></span>
        <span class="absolute top-36 -right-[3px] h-20 w-[3px] rounded-r bg-[#1d1d1f]" aria-hidden="true"></span>

        <div class="relative h-[35.5rem] overflow-hidden rounded-[2.45rem] bg-bg text-text" aria-hidden="true">
            {{-- Statusbalk met de uitsparing --}}
            <div class="relative flex h-11 items-center justify-between px-7 text-[0.6875rem] font-semibold">
                <span>9:41</span>
                {{-- De uitsparing; met `island` klapt hij af en toe open met een melding --}}
                <span @class(['absolute top-2.5 left-1/2 z-10 flex h-6 w-24 -translate-x-1/2 items-center justify-center overflow-hidden rounded-full bg-[#1d1d1f]', 'island' => $island])>
                    @if ($island)
                        <span class="island-text flex items-center gap-1.5 text-[0.625rem] font-semibold whitespace-nowrap text-white"><span class="size-1.5 rounded-full bg-[#30d158]"></span>{{ $island }}</span>
                    @endif
                </span>
                <span class="flex items-center gap-1">
                    <svg width="16" height="11" viewBox="0 0 16 11" fill="currentColor"><rect x="0" y="7" width="3" height="4" rx="0.7"/><rect x="4.3" y="5" width="3" height="6" rx="0.7"/><rect x="8.6" y="2.5" width="3" height="8.5" rx="0.7"/><rect x="12.9" y="0" width="3" height="11" rx="0.7"/></svg>
                    <svg width="24" height="11" viewBox="0 0 24 11" fill="none"><rect x="0.5" y="0.5" width="20" height="10" rx="3" stroke="currentColor" opacity="0.4"/><rect x="2" y="2" width="15" height="7" rx="1.6" fill="currentColor"/><rect x="21.5" y="3.5" width="2" height="4" rx="1" fill="currentColor" opacity="0.5"/></svg>
                </span>
            </div>
            {{ $slot }}
            {{-- Home-indicator --}}
            <span class="absolute bottom-1.5 left-1/2 h-1 w-24 -translate-x-1/2 rounded-full bg-text/80"></span>
        </div>
    </div>
    @if ($caption)
        <figcaption class="mt-4 text-center text-sm text-muted">{{ $label }}</figcaption>
    @endif
</figure>
