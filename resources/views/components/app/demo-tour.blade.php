{{--
    Welkom voor een bezoeker van de openbare demolink: waar je het eerst op tikt. Weg te klikken (onthouden in deze browser).
--}}
@props(['shop'])

@php
    $steps = [
        ['calendar', 'Bekijk de agenda', 'Elke kapper een eigen agenda', route('app.calendar'), true],
        ['plus', 'Maak een afspraak', 'Zoals bij een telefoontje', route('app.appointments.create'), true],
        ['trending-up', 'Bekijk de omzet', 'Per dag, week, maand en kapper', route('app.revenue'), true],
        ['store', 'Boek als klant', 'Je eigen boekingspagina', $shop->bookingUrl(), false],
    ];
@endphp

<div x-data="{ hidden: (() => { try { return localStorage.getItem('barbercuts:demo-tour') === @js((string) $shop->id) } catch (error) { return false } })() }"
    x-show="! hidden" x-transition.opacity.duration.200ms>
    <section class="relative overflow-hidden rounded-[1.75rem] bg-[#1d1d1f] p-5 text-white shadow-pop md:p-6" aria-labelledby="demo-tour-title">
        <span class="pointer-events-none absolute inset-0 bg-[radial-gradient(22rem_12rem_at_90%_0%,rgb(10_132_255/0.4),transparent_70%)]" aria-hidden="true"></span>
        <button type="button" class="absolute top-2 right-2 flex size-11 items-center justify-center rounded-full text-white/70 hover:text-white" aria-label="Verbergen"
            x-on:click="hidden = true; try { localStorage.setItem('barbercuts:demo-tour', @js((string) $shop->id)) } catch (error) {}">
            <x-ui.icon name="x" size="20" />
        </button>

        <p class="relative text-xs font-semibold text-white/70">Welkom in je demozaak</p>
        <h2 id="demo-tour-title" class="relative mt-1 pr-8 text-xl font-bold tracking-tight">Dit is {{ $shop->name }}, met een volle week</h2>
        <p class="relative mt-1 text-sm text-white/70">Alles werkt echt, maar niemand krijgt een bericht. Begin hier:</p>

        <div class="relative mt-4 grid grid-cols-2 gap-2 lg:grid-cols-4">
            @foreach ($steps as [$icon, $title, $text, $href, $navigate])
                <a href="{{ $href }}" @if ($navigate) wire:navigate @else target="_blank" rel="noopener" @endif
                    class="flex min-h-11 flex-col gap-1 rounded-2xl bg-white/10 p-3 transition-colors hover:bg-white/15 active:bg-white/20">
                    <x-ui.icon :name="$icon" size="20" />
                    <span class="text-sm font-semibold">{{ $title }}</span>
                    <span class="text-xs text-white/70">{{ $text }}</span>
                </a>
            @endforeach
        </div>

        <div class="relative mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-white/15 pt-4">
            <p class="text-sm text-white/80">Bevalt het? Maak dit je echte zaak: je diensten, prijzen en openingstijden gaan mee.</p>
            <a href="{{ route('onboarding') }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-pill bg-white px-4 text-sm font-semibold text-[#1d1d1f]">
                Maak dit mijn zaak <x-ui.icon name="arrow-right" size="16" />
            </a>
        </div>
    </section>
</div>
