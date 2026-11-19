@php
    $price = money($plan->price_cents ?? 1000);
    $trial = $plan->trial_days ?? 14;

    $faq = [
        ['Moet ik een creditcard invullen om te beginnen?', 'Nee. Je meldt je aan en kunt direct aan de slag.'],
        ['Zijn er kosten per kapper of per afspraak?', 'Nee. Je betaalt één prijs, ongeacht hoeveel kappers, klanten en afspraken je hebt.'],
        ['Hoe betaal ik?', 'Met iDEAL en een automatische maandelijkse incasso via Mollie. Opzeggen kan altijd; je kunt werken tot het einde van de periode die je betaald hebt.'],
        ['Wat gebeurt er met mijn gegevens als ik stop?', 'We verwijderen niets zomaar. Je kunt je gegevens laten verwijderen via de contactpagina.'],
        ['Kan ik eerst rustig rondkijken?', 'Ja. Meld je gratis aan, zet je diensten en openingstijden klaar en bekijk je eigen boekingspagina voordat je de link met klanten deelt.'],
    ];
@endphp

<x-layouts::platform title="Prijzen" description="Eén prijs voor je hele kapperszaak: onbeperkt kappers, klanten en afspraken. Geen opstartkosten.">
    <div class="relative overflow-x-clip">
        <div class="hero-glow pointer-events-none absolute inset-x-0 top-0 h-[34rem]" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-3xl px-4 pt-10 pb-16 md:pt-16 md:pb-24">
            <div class="text-center">
                <p class="rise-in text-sm font-semibold text-link">Prijzen</p>
                <h1 class="rise-in mt-1 text-[2.25rem] leading-[1.1] font-bold tracking-tight md:text-6xl" style="--rise-delay: 80ms">Eén prijs.<br><span class="text-shine">Alles erbij.</span></h1>
                <p class="rise-in mx-auto mt-4 max-w-md text-base text-muted md:text-lg" style="--rise-delay: 180ms">Geen opstartkosten, geen kosten per kapper of per afspraak.</p>
            </div>

            <div class="rise-in mt-10 overflow-hidden rounded-[2rem] bg-card shadow-pop ring-1 ring-border/70" style="--rise-delay: 280ms">
                <div class="p-6 text-center md:p-10">
                    <p class="text-sm font-medium text-muted">{{ $plan->name ?? 'BarberCuts' }}</p>
                    <p class="mt-1 text-6xl font-bold tracking-tight tabular-nums md:text-7xl">{{ $price }}</p>
                    <p class="text-base font-medium text-muted">per maand</p>
                    <p class="mx-auto mt-4 inline-flex items-center gap-2 rounded-pill bg-gold-soft px-3.5 py-1.5 text-sm font-semibold text-gold-ink">
                        @if ($billing)
                            De eerste {{ $trial }} dagen gratis
                        @else
                            Tijdens de lancering gratis
                        @endif
                    </p>
                    <p class="mx-auto mt-3 max-w-sm text-sm text-muted">
                        @if ($billing)
                            Daarna maandelijks opzegbaar.
                        @else
                            Je hoort het ruim van tevoren voordat daar verandering in komt. Betalen via Mollie staat tijdelijk uit.
                        @endif
                    </p>
                    <div class="mx-auto mt-6 max-w-xs">
                        <x-ui.button :href="route('onboarding')" full wire:navigate class="!rounded-pill">Start gratis</x-ui.button>
                    </div>
                </div>

                <ul class="grid gap-px border-t border-border/70 bg-border/70 sm:grid-cols-2">
                    @foreach ([
                        ['store', 'Eigen boekingspagina met je logo en kleuren'],
                        ['users', 'Onbeperkt kappers, elk met een eigen agenda'],
                        ['calendar', 'Onbeperkt afspraken en klanten'],
                        ['trending-up', 'Omzet per dag, week, maand en jaar'],
                        ['bell', 'Herinneringen aan klanten'],
                        ['gift', 'Spaarkaart, reviews en wachtlijst'],
                    ] as [$icon, $feature])
                        <li class="flex items-center gap-3 bg-card px-5 py-4 text-sm font-medium">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-surface text-muted"><x-ui.icon :name="$icon" size="18" /></span>{{ $feature }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="mt-5 text-center text-sm text-muted">App op je telefoon, zonder installatie uit een winkel. Opzeggen kan altijd.</p>

            {{-- Wat het jou oplevert --}}
            <div class="mt-14 rounded-[1.75rem] bg-[#1d1d1f] p-6 text-center text-white md:p-10" data-reveal>
                <p class="text-sm text-white/60">Reken even mee</p>
                <p class="mx-auto mt-2 max-w-md text-2xl leading-8 font-bold tracking-tight md:text-3xl md:leading-9">Eén klant minder die niet komt opdagen, en je maand is al terugverdiend.</p>
                <p class="mx-auto mt-3 max-w-md text-sm text-white/70">Met herinneringen en een wachtlijst die vrijgekomen plekken opvult.</p>
            </div>

            <h2 class="mt-14 text-center text-[1.75rem] leading-9 font-bold tracking-tight md:text-4xl" data-reveal>Veelgestelde vragen</h2>
            <x-platform.faq :items="$faq" class="mt-6" data-reveal />
            <p class="mt-5 text-center text-sm text-muted">Nog een vraag? <a class="font-medium text-link underline" href="{{ route('contact') }}" wire:navigate>Neem contact op</a>.</p>
        </div>
    </div>
</x-layouts::platform>
