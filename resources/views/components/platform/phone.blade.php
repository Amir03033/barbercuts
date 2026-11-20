{{--
    Telefoon voor de voorbeeldschermen op de landingspagina: een titanium rand, een zwarte rand eromheen, het eiland
    bovenin met de statusbalk op dezelfde hoogte, en een vleugje glans op het glas. Puur decoratief; de inhoud is fictief.
    label: omschrijving voor schermlezers (en onderschrift als caption = true). island: tekst van de melding in het eiland.
--}}
@props(['label', 'caption' => false, 'island' => null])

<figure {{ $attributes->class('mx-auto w-[17.5rem] shrink-0') }}>
    <div class="relative rounded-[3.15rem] bg-[linear-gradient(150deg,#6e6e73_0%,#2c2c2e_22%,#1c1c1e_55%,#48484a_85%,#2c2c2e_100%)] p-[3px] shadow-[0_50px_100px_-40px_rgb(0_0_0/0.55),0_24px_48px_-28px_rgb(0_0_0/0.35)]"
        role="img" aria-label="{{ $label }}">
        {{-- Zijknoppen: actieknop en volume links, aan/uit rechts --}}
        <span class="absolute top-[5.5rem] -left-[2px] h-6 w-[3px] rounded-l-sm bg-[linear-gradient(90deg,#3a3a3c,#5a5a5e)]" aria-hidden="true"></span>
        <span class="absolute top-[8rem] -left-[2px] h-11 w-[3px] rounded-l-sm bg-[linear-gradient(90deg,#3a3a3c,#5a5a5e)]" aria-hidden="true"></span>
        <span class="absolute top-[11.25rem] -left-[2px] h-11 w-[3px] rounded-l-sm bg-[linear-gradient(90deg,#3a3a3c,#5a5a5e)]" aria-hidden="true"></span>
        <span class="absolute top-[9.5rem] -right-[2px] h-16 w-[3px] rounded-r-sm bg-[linear-gradient(270deg,#3a3a3c,#5a5a5e)]" aria-hidden="true"></span>

        <div class="rounded-[3rem] bg-black p-[0.5rem]">
            <div class="relative aspect-[9/19.5] overflow-hidden rounded-[2.5rem] bg-bg text-text" aria-hidden="true">
                {{-- Statusbalk: tijd en iconen op de hoogte van het midden van het eiland --}}
                <div class="relative flex h-[2.85rem] items-center justify-between pr-5 pl-7 text-[0.75rem] font-semibold tracking-tight">
                    <span>9:41</span>
                    <span class="flex items-center gap-[0.3rem]">
                        <svg width="17" height="11" viewBox="0 0 17 11" fill="currentColor"><rect x="0" y="7.5" width="3" height="3.5" rx="0.8"/><rect x="4.6" y="5.2" width="3" height="5.8" rx="0.8"/><rect x="9.2" y="2.7" width="3" height="8.3" rx="0.8"/><rect x="13.8" y="0" width="3" height="11" rx="0.8"/></svg>
                        <svg width="15" height="11" viewBox="0 0 15 11" fill="currentColor"><path d="M7.5 2.3c2 0 3.9.8 5.3 2.1l1.2-1.2A9 9 0 0 0 7.5.6 9 9 0 0 0 1 3.2l1.2 1.2a7.5 7.5 0 0 1 5.3-2.1Zm0 3.3c1.1 0 2.2.4 3 1.2l1.2-1.2a5.9 5.9 0 0 0-8.4 0l1.2 1.2c.8-.8 1.9-1.2 3-1.2Zm0 3.3c.4 0 .8.2 1.1.5L7.5 10.5 6.4 9.4c.3-.3.7-.5 1.1-.5Z"/></svg>
                        <svg width="25" height="12" viewBox="0 0 25 12" fill="none"><rect x="0.5" y="0.5" width="21" height="11" rx="3.4" stroke="currentColor" opacity="0.35"/><rect x="2" y="2" width="16.5" height="8" rx="2" fill="currentColor"/><path d="M23 4v4c.8-.3 1.3-1.1 1.3-2S23.8 4.3 23 4Z" fill="currentColor" opacity="0.4"/></svg>
                    </span>
                </div>

                {{-- Het eiland; met `island` klapt het af en toe open met een melding --}}
                <span @class(['absolute top-[0.6rem] left-1/2 z-10 flex h-[1.6rem] w-[5rem] -translate-x-1/2 items-center justify-center overflow-hidden rounded-full bg-black', 'island' => $island])>
                    @if ($island)
                        <span class="island-text flex items-center gap-1.5 text-[0.625rem] font-semibold whitespace-nowrap text-white"><span class="size-1.5 rounded-full bg-[#30d158]"></span>{{ $island }}</span>
                    @endif
                </span>

                {{ $slot }}

                {{-- Glans op het glas --}}
                <span class="pointer-events-none absolute inset-0 rounded-[2.5rem] bg-[linear-gradient(115deg,rgb(255_255_255/0.22)_0%,rgb(255_255_255/0)_28%)]"></span>
                {{-- Home-indicator --}}
                <span class="absolute bottom-[0.45rem] left-1/2 h-[0.3rem] w-[6.5rem] -translate-x-1/2 rounded-full bg-text/85"></span>
            </div>
        </div>
    </div>
    @if ($caption)
        <figcaption class="mt-4 text-center text-sm text-muted">{{ $label }}</figcaption>
    @endif
</figure>
