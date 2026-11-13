@php
    $plan = \App\Models\Plan::current();
    $billing = \App\Support\Features::billing();
    $price = $plan ? money($plan->price_cents) : money(1000);
    $trial = $plan->trial_days ?? 14;

    // Fictieve afspraken voor de voorbeeldschermen
    $day = [['10:00', 'Daan de Vries', 'Knippen', '#0071e3'], ['10:30', 'Yusuf Kaya', 'Knippen + baard', '#34c759'], ['11:15', 'Sem Bakker', 'Skin fade', '#0071e3'], ['12:00', 'Lars Visser', 'Baard', '#ff9f0a']];
@endphp

<div class="overflow-x-clip">
    {{-- Kop --}}
    <section class="relative">
        <div class="hero-glow pointer-events-none absolute inset-0" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-5xl px-4 pt-10 text-center md:pt-20">
            <p class="rise-in mx-auto inline-flex items-center gap-2 rounded-pill bg-card/80 px-3.5 py-1.5 text-xs font-semibold text-muted shadow-soft ring-1 ring-border backdrop-blur">
                <span class="size-1.5 rounded-full bg-switch"></span>
                {{ $billing ? $trial.' dagen gratis proberen' : 'Tijdens de lancering gratis' }}
            </p>
            <h1 class="mx-auto mt-5 max-w-3xl text-[2.5rem] leading-[1.05] font-bold tracking-tight text-text md:text-7xl">
                <span class="rise-in block" style="--rise-delay: 80ms">Je hele kapperszaak.</span>
                <span class="rise-in text-shine block" style="--rise-delay: 200ms">In je broekzak.</span>
            </h1>
            <p class="rise-in mx-auto mt-5 max-w-xl text-base text-muted md:text-xl md:leading-8" style="--rise-delay: 320ms">
                Afspraken, agenda en omzet voor je kapperszaak, in één app. Klanten boeken zelf, jij knipt.
            </p>
            <div class="rise-in mx-auto mt-7 flex max-w-sm flex-col gap-3 sm:max-w-none sm:flex-row sm:justify-center" style="--rise-delay: 420ms">
                <x-ui.button :href="route('onboarding')" wire:navigate class="!rounded-pill sm:!px-8">Start gratis</x-ui.button>
                <x-ui.button :href="route('pricing')" variant="secondary" wire:navigate class="!rounded-pill sm:!px-8">Bekijk de prijs</x-ui.button>
            </div>
            <p class="mt-4 text-sm text-muted">
                @if ($billing)
                    {{ $price }} per maand · {{ $trial }} dagen gratis · opzeggen kan altijd
                @else
                    Tijdens de lancering gratis · geen creditcard nodig
                @endif
            </p>

            {{-- De app: het overzicht van vandaag, met wat er op een dag binnenkomt --}}
            <div class="phone-in relative mx-auto mt-12 w-fit pb-4 md:mt-16">
                <x-platform.phone label="Het overzicht van vandaag in de app" island="Nieuwe boeking · 14:30">
                    <div class="px-4 pt-1 text-left">
                        <p class="text-[1.375rem] leading-7 font-bold tracking-tight">Goedemorgen, Sam</p>
                        <p class="text-[0.6875rem] text-muted">Studio Fade · vrijdag</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div class="rounded-2xl bg-card p-3 shadow-soft">
                                <p class="text-[0.625rem] font-medium text-muted">Omzet vandaag</p>
                                <p class="mt-0.5 text-xl font-bold tracking-tight tabular-nums">€ 385,00</p>
                                <p class="mt-1 inline-flex rounded-pill bg-completed/10 px-1.5 py-0.5 text-[0.5625rem] font-semibold text-completed">+18%</p>
                            </div>
                            <div class="rounded-2xl bg-card p-3 shadow-soft">
                                <p class="text-[0.625rem] font-medium text-muted">Afspraken</p>
                                <p class="mt-0.5 text-xl font-bold tracking-tight">14</p>
                                <p class="mt-1 text-[0.5625rem] text-muted">9 nog te gaan</p>
                            </div>
                        </div>
                        <p class="mt-4 mb-2 text-xs font-semibold">Vandaag</p>
                        <div class="space-y-1.5">
                            @foreach ($day as [$time, $name, $service, $color])
                                <div class="flex items-center gap-2.5 rounded-2xl bg-card p-2.5 shadow-soft">
                                    <span class="w-9 text-center text-xs font-bold tabular-nums">{{ $time }}</span>
                                    <span class="h-8 w-1 rounded-full" style="background-color: {{ $color }}"></span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-xs font-semibold">{{ $name }}</span><span class="block truncate text-[0.625rem] text-muted">{{ $service }}</span></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 flex h-16 items-start justify-around border-t border-border/70 bg-card/85 px-3 pt-2 text-[0.5625rem] font-medium text-muted backdrop-blur">
                        <span class="flex flex-col items-center gap-0.5 text-link"><x-ui.icon name="layout-dashboard" size="18" />Overzicht</span>
                        <span class="flex flex-col items-center gap-0.5"><x-ui.icon name="calendar" size="18" />Agenda</span>
                        <span class="flex size-9 items-center justify-center rounded-full bg-accent text-on-accent"><x-ui.icon name="plus" size="18" /></span>
                        <span class="flex flex-col items-center gap-0.5"><x-ui.icon name="users" size="18" />Klanten</span>
                        <span class="flex flex-col items-center gap-0.5"><x-ui.icon name="menu" size="18" />Meer</span>
                    </div>
                </x-platform.phone>

                {{-- Zwevende meldingen (decoratief) --}}
                <div class="float-slow absolute top-24 -left-4 flex w-52 items-center gap-2.5 rounded-2xl bg-card/90 p-3 text-left shadow-pop ring-1 ring-border backdrop-blur-xl sm:-left-40" aria-hidden="true">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-accent text-on-accent"><x-ui.icon name="calendar" size="18" /></span>
                    <span class="min-w-0"><span class="block text-xs font-semibold">Nieuwe boeking</span><span class="block truncate text-[0.6875rem] text-muted">Lars · vandaag 14:30</span></span>
                </div>
                <div class="float-slower absolute top-64 -right-4 flex w-48 items-center gap-2.5 rounded-2xl bg-card/90 p-3 text-left shadow-pop ring-1 ring-border backdrop-blur-xl sm:-right-36" aria-hidden="true">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-star/15 text-star"><x-ui.icon name="star" size="18" /></span>
                    <span class="min-w-0"><span class="block text-xs font-semibold">Nieuwe review</span><span class="block text-[0.6875rem] text-muted">5 sterren · “Strakke fade”</span></span>
                </div>
                <div class="float-slow absolute bottom-28 -left-2 hidden w-44 items-center gap-2.5 rounded-2xl bg-card/90 p-3 text-left shadow-pop ring-1 ring-border backdrop-blur-xl sm:-left-32 sm:flex" aria-hidden="true">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-completed/15 text-completed"><x-ui.icon name="check" size="18" /></span>
                    <span class="min-w-0"><span class="block text-xs font-semibold">Voltooid</span><span class="block text-[0.6875rem] text-muted">€ 35,00 · pin</span></span>
                </div>
            </div>
        </div>
    </section>

    {{-- Lopende band met diensten, tussen twee draaiende kapperspalen --}}
    <div class="relative mt-10 flex items-center gap-4 border-y border-border/70 bg-card py-4" aria-hidden="true">
        <span class="barber-pole ml-4 h-10 w-3.5 shrink-0 rounded-full"></span>
        <div class="min-w-0 flex-1 overflow-hidden [mask-image:linear-gradient(90deg,transparent,black_12%,black_88%,transparent)]">
            <div class="marquee flex w-max gap-8 text-2xl font-bold tracking-tight whitespace-nowrap text-text/25 md:text-4xl">
                @foreach (range(1, 2) as $copy)
                    @foreach (['Knippen', 'Skin fade', 'Baard', 'Contouren', 'Tondeuse', 'Kinderen', 'Wassen', 'Stylen', 'Hot towel'] as $word)
                        <span>{{ $word }}</span><span class="text-accent/60">✂</span>
                    @endforeach
                @endforeach
            </div>
        </div>
        <span class="barber-pole mr-4 h-10 w-3.5 shrink-0 rounded-full"></span>
    </div>

    {{-- Alles in één app --}}
    <section class="mx-auto max-w-5xl px-4 py-16 md:py-24">
        <p class="text-sm font-semibold text-link" data-reveal>Zo ziet het eruit</p>
        <h2 class="mt-1 max-w-2xl text-[2rem] leading-10 font-bold tracking-tight md:text-5xl md:leading-[1.1]" data-reveal>Alles wat je zaak nodig heeft. Niets wat in de weg zit.</h2>

        <div class="mt-8 grid gap-4 md:grid-cols-6">
            {{-- Zelf proberen: kies een dag en tijd en boek (er wordt niets opgeslagen) --}}
            <div class="relative overflow-hidden rounded-[1.75rem] bg-card p-6 shadow-soft ring-1 ring-border/70 md:col-span-4" data-reveal
                x-data="{ day: 14, time: '14:30', booked: false, book() { this.booked = true; setTimeout(() => this.booked = false, 2600); } }">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-xl font-bold tracking-tight">Klanten boeken zelf</h3>
                    <span class="shrink-0 rounded-pill bg-gold-soft px-2.5 py-1 text-xs font-semibold text-gold-ink">Probeer het</span>
                </div>
                <p class="mt-1 max-w-md text-base text-muted">Een eigen link voor je zaak. Klanten kiezen dienst, kapper en tijd in een halve minuut, ook zonder account.</p>
                <div class="mt-6 flex gap-2">
                    @foreach (['wo' => 12, 'do' => 13, 'vr' => 14, 'za' => 15, 'di' => 18] as $name => $number)
                        <button type="button" x-on:click="day = {{ $number }}" :class="day === {{ $number }} ? 'bg-accent text-on-accent' : 'bg-surface hover:bg-border'" aria-label="{{ $name }} {{ $number }}"
                            class="flex h-16 flex-1 flex-col items-center justify-center rounded-2xl text-xs font-medium transition-[background-color,color,transform] duration-200 ease-out-soft active:scale-95 {{ $number === 14 ? 'bg-accent text-on-accent' : 'bg-surface' }}"><span class="opacity-70">{{ $name }}</span><span class="text-lg font-bold">{{ $number }}</span></button>
                    @endforeach
                </div>
                <div class="mt-2 grid grid-cols-4 gap-2">
                    @foreach (['13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'] as $time)
                        <button type="button" x-on:click="time = '{{ $time }}'" :class="time === '{{ $time }}' ? 'bg-accent text-on-accent' : 'bg-surface hover:bg-border'"
                            class="flex h-11 items-center justify-center rounded-xl text-sm font-medium tabular-nums transition-[background-color,color,transform] duration-200 ease-out-soft active:scale-95 {{ $time === '14:30' ? 'bg-accent text-on-accent' : 'bg-surface' }}">{{ $time }}</button>
                    @endforeach
                </div>
                <button type="button" x-on:click="book()" class="mt-3 flex h-12 w-full items-center justify-center rounded-xl bg-accent text-base font-semibold text-on-accent transition-transform duration-150 ease-out-soft active:scale-[0.98]">
                    Boek <span class="ml-1 tabular-nums" x-text="time">14:30</span>
                </button>

                <div x-show="booked" x-cloak x-transition.opacity.duration.200ms class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-card/95 backdrop-blur" role="status">
                    <span class="pop-check flex size-16 items-center justify-center rounded-full bg-completed text-white"><x-ui.icon name="check" size="32" /></span>
                    <p class="text-xl font-bold tracking-tight">Je afspraak staat vast</p>
                    <p class="text-sm text-muted">Zo snel gaat het voor je klant.</p>
                </div>
            </div>

            <div class="rounded-[1.75rem] bg-[#1d1d1f] p-6 text-white md:col-span-2" data-reveal style="--reveal-delay: 80ms">
                <h3 class="text-xl font-bold tracking-tight">Omzet in één oogopslag</h3>
                <p class="mt-1 text-base text-white/60">Dag, week of maand, met vergelijking. Een kapper ziet alleen zijn eigen omzet; jij het totaal.</p>
                <p class="mt-6 text-4xl font-bold tracking-tight tabular-nums" x-data="bcCount(2845)">€ <span x-text="label">2.845</span></p>
                <p class="text-sm text-[#30d158]">+12% t.o.v. vorige week</p>
                <div class="mt-4 flex h-20 items-end gap-1.5" aria-hidden="true">
                    @foreach ([35, 62, 48, 80, 100, 72, 20] as $height)
                        <span class="animate-grow flex-1 origin-bottom rounded-t-md bg-gradient-to-t from-[#0a84ff] to-[#64d2ff]" style="height: {{ $height }}%; animation-delay: {{ $loop->index * 70 }}ms"></span>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[1.75rem] bg-card p-6 shadow-soft ring-1 ring-border/70 md:col-span-2" data-reveal>
                <h3 class="text-xl font-bold tracking-tight">Een agenda per kapper</h3>
                <p class="mt-1 text-base text-muted">Eigen werktijden, pauzes en vrije dagen. Een telefonische afspraak staat er in een paar tikken in.</p>
                <div class="mt-5 grid grid-cols-3 gap-1.5 text-[0.6875rem] font-semibold" aria-hidden="true">
                    @foreach ([['Sam', '#0071e3', [10, 16, 12]], ['Mo', '#34c759', [14, 10, 18]], ['Daan', '#ff9f0a', [8, 20, 10]]] as [$name, $color, $blocks])
                        <div class="space-y-1.5">
                            <p class="flex items-center gap-1 text-muted"><span class="size-2 rounded-full" style="background: {{ $color }}"></span>{{ $name }}</p>
                            @foreach ($blocks as $size)
                                <span class="block rounded-lg" style="height: {{ $size * 2.4 }}px; background: color-mix(in srgb, {{ $color }} 22%, transparent); border-left: 3px solid {{ $color }}"></span>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[1.75rem] bg-card p-6 shadow-soft ring-1 ring-border/70 md:col-span-2" data-reveal>
                <span class="flex size-11 items-center justify-center rounded-2xl bg-star/15 text-star"><x-ui.icon name="star" /></span>
                <h3 class="mt-4 text-xl font-bold tracking-tight">Reviews die kloppen</h3>
                <p class="mt-1 text-base text-muted">Alleen klanten die er geweest zijn kunnen beoordelen. Goede reviews zetten je bovenaan bij kappers in de buurt.</p>
            </div>

            <div class="rounded-[1.75rem] bg-card p-6 shadow-soft ring-1 ring-border/70 md:col-span-2" data-reveal>
                <span class="flex size-11 items-center justify-center rounded-2xl bg-switch/15 text-completed"><x-ui.icon name="bell" /></span>
                <h3 class="mt-4 text-xl font-bold tracking-tight">Minder no-shows</h3>
                <p class="mt-1 text-base text-muted">Automatische herinneringen voor elke afspraak. Verplaatsen of annuleren doet de klant zelf, met één tik.</p>
            </div>

            <div class="rounded-[1.75rem] bg-card p-6 shadow-soft ring-1 ring-border/70 md:col-span-3" data-reveal>
                <span class="flex size-11 items-center justify-center rounded-2xl bg-gold-soft text-gold-ink"><x-ui.icon name="gift" /></span>
                <h3 class="mt-4 text-xl font-bold tracking-tight">Je eigen spaarkaart</h3>
                <p class="mt-1 text-base text-muted">Bepaal zelf het aantal stempels en de beloning. Stempelen gaat vanzelf bij elke afspraak.</p>
                <div class="mt-4 flex flex-wrap gap-1.5" aria-hidden="true">
                    @foreach (range(1, 10) as $stamp)
                        <span class="flex size-8 items-center justify-center rounded-full {{ $stamp <= 7 ? 'bg-accent text-on-accent' : 'bg-surface' }}">@if ($stamp <= 7)<x-ui.icon name="check" size="14" />@endif</span>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[1.75rem] bg-card p-6 shadow-soft ring-1 ring-border/70 md:col-span-3" data-reveal>
                <span class="flex size-11 items-center justify-center rounded-2xl bg-[#bf5af2]/15 text-[#8944ab]"><x-ui.icon name="heart" /></span>
                <h3 class="mt-4 text-xl font-bold tracking-tight">Klanten komen terug</h3>
                <p class="mt-1 text-base text-muted">Wie te lang wegblijft krijgt vanzelf een bericht. Zit een dag vol, dan vult de wachtlijst een vrijgekomen plek.</p>
            </div>
        </div>
    </section>

    {{-- Rekenhulp: wat kosten lege stoelen? Rekent alleen met wat de bezoeker zelf instelt. --}}
    <section class="mx-auto max-w-5xl px-4 pb-16 md:pb-24">
        <div class="overflow-hidden rounded-[2rem] bg-card p-6 shadow-soft ring-1 ring-border/70 md:p-10" data-reveal
            x-data="{ noShows: 4, price: 28, get month() { return Math.round(this.noShows * this.price * 52 / 12); }, get year() { return this.noShows * this.price * 52; }, euro(value) { return '€ ' + value.toLocaleString('nl-NL'); } }">
            <div class="grid gap-8 md:grid-cols-2 md:items-center">
                <div>
                    <p class="text-sm font-semibold text-link">Reken het even uit</p>
                    <h2 class="mt-1 text-[1.75rem] leading-9 font-bold tracking-tight md:text-4xl">Wat kost een lege stoel jou?</h2>
                    <p class="mt-3 text-base text-muted">Elke klant die niet komt opdagen is een stoel die leeg blijft. Schuif en zie wat dat optelt.</p>

                    <label class="mt-6 block">
                        <span class="flex items-baseline justify-between text-sm font-medium"><span>Klanten die niet komen, per week</span><span class="text-lg font-bold tabular-nums" x-text="noShows">4</span></span>
                        <input type="range" min="0" max="15" step="1" x-model.number="noShows" class="bc-range" :style="`--fill: ${noShows / 15 * 100}%`" />
                    </label>
                    <label class="mt-2 block">
                        <span class="flex items-baseline justify-between text-sm font-medium"><span>Gemiddelde prijs per afspraak</span><span class="text-lg font-bold tabular-nums" x-text="euro(price)">€ 28</span></span>
                        <input type="range" min="15" max="60" step="1" x-model.number="price" class="bc-range" :style="`--fill: ${(price - 15) / 45 * 100}%`" />
                    </label>
                </div>

                <div class="rounded-[1.5rem] bg-[#1d1d1f] p-6 text-center text-white md:p-8">
                    <p class="text-sm text-white/60">Lege stoelen per maand</p>
                    <p class="mt-1 text-5xl font-bold tracking-tight tabular-nums md:text-6xl" x-text="euro(month)">€ 485</p>
                    <p class="mt-2 text-sm text-white/60">Dat is <span class="font-semibold text-white tabular-nums" x-text="euro(year)">€ 5.824</span> per jaar.</p>
                    <p class="mt-5 text-sm text-white/80">Automatische herinneringen en een wachtlijst helpen om die stoelen te vullen.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Jouw pagina, jouw stijl --}}
    <section class="bg-card">
        <div class="mx-auto grid max-w-5xl items-center gap-10 px-4 py-16 md:grid-cols-2 md:py-24">
            <div data-reveal>
                <p class="text-sm font-semibold text-link">Jouw pagina</p>
                <h2 class="mt-1 text-[2rem] leading-10 font-bold tracking-tight md:text-5xl md:leading-[1.1]">Jouw zaak. Jouw kleuren.</h2>
                <p class="mt-4 text-base text-muted md:text-lg">Elke zaak krijgt een eigen boekingspagina op barbercuts.nl/jouwzaak. Met je logo, je foto's, je eigen kleur en een banner voor acties.</p>
                <ul class="mt-6 space-y-3 text-base">
                    @foreach (['Licht of donker thema en je eigen accentkleur', 'Omslagfoto, logo en een fotogalerij van je werk', 'Banner voor een actie of mededeling', 'Link voor in je Instagram-bio en een QR-code voor in de zaak'] as $line)
                        <li class="flex gap-3"><x-ui.icon name="check" size="20" class="mt-0.5 shrink-0 text-completed" /><span>{{ $line }}</span></li>
                    @endforeach
                </ul>
            </div>

            <x-platform.phone label="De boekingspagina van een zaak" data-reveal style="--reveal-delay: 120ms">
                <div data-theme="dark" style="--accent: #ff9f0a; --on-accent: #1d1d1f" class="-mt-11 h-full bg-bg pt-11 text-left text-text">
                    <div class="h-28 bg-gradient-to-br from-[#ff9f0a] to-[#ff375f]"></div>
                    <div class="px-4">
                        <span class="-mt-7 flex size-14 items-center justify-center rounded-2xl border-4 border-bg bg-accent text-lg font-bold text-on-accent">S</span>
                        <p class="mt-2 text-xl font-bold tracking-tight">Studio Fade</p>
                        <p class="flex items-center gap-1.5 text-[0.6875rem] text-muted"><x-ui.stars :rating="5" size="11" /> 4,9 · 128 reviews</p>
                        <p class="mt-3 rounded-2xl bg-accent px-3 py-2 text-xs font-semibold text-on-accent">Deze maand: 10% korting voor studenten</p>
                        <div class="mt-3 divide-y divide-border overflow-hidden rounded-2xl bg-card text-xs">
                            @foreach ([['Knippen', '30 min', '€ 25,00'], ['Knippen + baard', '45 min', '€ 35,00'], ['Skin fade', '45 min', '€ 32,00']] as [$name, $duration, $amount])
                                <p class="flex items-center justify-between px-3 py-2.5"><span><span class="block font-semibold">{{ $name }}</span><span class="text-[0.625rem] text-muted">{{ $duration }}</span></span><span class="font-semibold tabular-nums">{{ $amount }}</span></p>
                            @endforeach
                        </div>
                        <p class="mt-4 flex h-11 items-center justify-center rounded-xl bg-accent text-sm font-semibold text-on-accent">Afspraak maken</p>
                    </div>
                </div>
            </x-platform.phone>
        </div>
    </section>

    {{-- Zo begin je --}}
    <section class="mx-auto max-w-5xl px-4 py-16 md:py-24">
        <h2 class="text-center text-[2rem] leading-10 font-bold tracking-tight md:text-5xl" data-reveal>Klaar in drie minuten</h2>
        <div class="mt-10 grid gap-4 md:grid-cols-3">
            @foreach ([['Meld je zaak aan', 'Naam, plaats en je eigen link. Dat is alles.'], ['Kies diensten en tijden', 'Vink de voorgestelde diensten aan en pas je openingstijden aan.'], ['Deel je boekingslink', 'Via WhatsApp, in je Instagram-bio of met de QR-code in je zaak.']] as $index => [$title, $text])
                <div class="rounded-[1.75rem] bg-card p-6 shadow-soft ring-1 ring-border/70" data-reveal style="--reveal-delay: {{ $index * 90 }}ms">
                    <span class="flex size-10 items-center justify-center rounded-full bg-accent text-base font-bold text-on-accent">{{ $index + 1 }}</span>
                    <h3 class="mt-4 text-xl font-bold tracking-tight">{{ $title }}</h3>
                    <p class="mt-1 text-base text-muted">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Voor klanten: best beoordeelde kappers (fase 7) --}}
    <section class="bg-card">
        <div class="mx-auto max-w-5xl px-4 py-16">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-[1.75rem] leading-9 font-bold tracking-tight md:text-4xl">Top beoordeelde kappers bij jou in de buurt</h2>
                    <p class="mt-1 text-base text-muted">Reviews van klanten die er echt geweest zijn.</p>
                </div>
                <x-ui.button :href="route('directory')" variant="secondary" size="sm" icon="map-pin" wire:navigate class="!rounded-pill">Zoek bij mij in de buurt</x-ui.button>
            </div>
            @if ($topShops->isNotEmpty())
                <div class="mt-6 grid gap-3 md:grid-cols-2">
                    @foreach ($topShops as $shop)
                        <x-platform.shop-card :shop="$shop" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- Afsluiter --}}
    <section class="px-4 py-16 md:py-24">
        <div class="relative mx-auto max-w-5xl overflow-hidden rounded-[2rem] bg-[#1d1d1f] px-6 py-14 text-center text-white md:py-20" data-reveal>
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(30rem_18rem_at_50%_0%,rgb(10_132_255/0.35),transparent_70%)]" aria-hidden="true"></div>
            <h2 class="relative text-[2rem] leading-10 font-bold tracking-tight md:text-5xl">Begin vandaag. Gratis.</h2>
            <p class="relative mx-auto mt-3 max-w-md text-base text-white/70">
                @if ($billing)
                    {{ $trial }} dagen gratis, daarna {{ $price }} per maand. Geen opstartkosten, opzeggen kan altijd.
                @else
                    Tijdens de lancering betaal je niets. Geen creditcard nodig.
                @endif
            </p>
            <div class="relative mt-7 flex justify-center">
                <a href="{{ route('onboarding') }}" wire:navigate class="inline-flex min-h-[3.125rem] items-center gap-2 rounded-pill bg-white px-8 text-base font-semibold text-[#1d1d1f] transition-transform duration-150 ease-out-soft active:scale-[0.98]">
                    Start gratis <x-ui.icon name="arrow-right" size="18" />
                </a>
            </div>
        </div>
    </section>
</div>
