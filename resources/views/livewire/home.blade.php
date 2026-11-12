@php
    $plan = \App\Models\Plan::current();
    $billing = \App\Support\Features::billing();
    $price = $plan ? money($plan->price_cents) : money(1000);
    $trial = $plan->trial_days ?? 14;
@endphp

<div>
    {{-- Kop --}}
    <section class="mx-auto max-w-5xl px-4 pt-10 pb-12 md:pt-20 md:pb-20">
        <div class="max-w-2xl">
            <h1 class="text-display font-bold tracking-tight text-text md:text-5xl md:leading-[1.1]">
                Afspraken, agenda en omzet voor je kapperszaak — op je telefoon
            </h1>
            <p class="mt-4 text-base text-muted md:text-lg">
                Klanten boeken zelf via je eigen link. Jij ziet je dag, je klanten en je omzet in één rustige app, en elke kapper heeft een eigen agenda.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <x-ui.button :href="route('onboarding')" wire:navigate>Start gratis</x-ui.button>
                <x-ui.button :href="route('pricing')" variant="secondary" wire:navigate>Bekijk de prijs</x-ui.button>
            </div>
            <p class="mt-4 text-sm text-muted">
                @if ($billing)
                    {{ $price }} per maand · {{ $trial }} dagen gratis · opzeggen kan altijd
                @else
                    Tijdens de lancering gratis · geen creditcard nodig
                @endif
            </p>
        </div>
    </section>

    {{-- Drie voordelen --}}
    <section class="border-y border-border bg-surface">
        <div class="mx-auto grid max-w-5xl gap-4 px-4 py-10 md:grid-cols-3">
            <x-ui.card flat class="space-y-2">
                <span class="flex size-11 items-center justify-center rounded-full bg-surface text-text"><x-ui.icon name="calendar" /></span>
                <h2 class="text-base font-semibold">Klanten boeken zelf</h2>
                <p class="text-sm text-muted">Een eigen link voor je zaak. Klanten kiezen dienst, kapper en tijd in een halve minuut, ook zonder account.</p>
            </x-ui.card>
            <x-ui.card flat class="space-y-2">
                <span class="flex size-11 items-center justify-center rounded-full bg-surface text-text"><x-ui.icon name="users" /></span>
                <h2 class="text-base font-semibold">Een agenda per kapper</h2>
                <p class="text-sm text-muted">Eigen werktijden, pauzes en vrije dagen. Je boekt een telefonische afspraak in een paar tikken.</p>
            </x-ui.card>
            <x-ui.card flat class="space-y-2">
                <span class="flex size-11 items-center justify-center rounded-full bg-surface text-text"><x-ui.icon name="euro" /></span>
                <h2 class="text-base font-semibold">Omzet in één oogopslag</h2>
                <p class="text-sm text-muted">Dag, week of maand met vergelijking. Een kapper ziet alleen zijn eigen omzet; jij het totaal.</p>
            </x-ui.card>
        </div>
    </section>

    {{-- Voorbeeldschermen (fictieve gegevens) --}}
    <section class="mx-auto max-w-5xl px-4 py-12">
        <h2 class="text-lg font-bold md:text-xl">Zo ziet het eruit</h2>
        <div class="mt-6 grid gap-10 sm:grid-cols-2">
            <x-platform.phone label="Jouw dag in één overzicht">
                <div class="px-3 pt-4">
                    <p class="text-xs font-semibold">Vandaag</p>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <div class="rounded-card border border-border p-2"><p class="text-[0.625rem] text-muted">Omzet</p><p class="text-lg font-bold tabular-nums">€ 245,00</p></div>
                        <div class="rounded-card border border-border p-2"><p class="text-[0.625rem] text-muted">Afspraken</p><p class="text-lg font-bold">9</p></div>
                    </div>
                    <div class="mt-3 space-y-2">
                        @foreach ([['10:00', 'Daan', 'Knippen', '#2563eb'], ['10:30', 'Yusuf', 'Knippen + baard', '#16a34a'], ['11:15', 'Sem', 'Baard', '#2563eb'], ['12:00', 'Lars', 'Kinderen', '#d97706']] as [$time, $name, $service, $color])
                            <div class="flex items-center gap-2 rounded-card border border-border p-2">
                                <span class="w-9 text-xs font-bold tabular-nums">{{ $time }}</span>
                                <span class="h-7 w-1 rounded-full" style="background-color: {{ $color }}"></span>
                                <span class="min-w-0"><span class="block truncate text-xs font-medium">{{ $name }}</span><span class="block truncate text-[0.625rem] text-muted">{{ $service }}</span></span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-platform.phone>

            <x-platform.phone label="Klanten boeken in een halve minuut">
                <div class="px-3 pt-4">
                    <p class="text-xs font-semibold">Kies een dag en tijd</p>
                    <div class="mt-2 flex gap-1.5">
                        @foreach (['wo 12', 'do 13', 'vr 14', 'za 15'] as $index => $day)
                            <span class="flex h-12 flex-1 items-center justify-center rounded-card border text-[0.6875rem] font-semibold {{ $index === 1 ? 'border-primary bg-primary text-white' : 'border-border' }}">{{ $day }}</span>
                        @endforeach
                    </div>
                    <p class="mt-4 mb-2 text-[0.625rem] font-semibold tracking-wide text-muted uppercase">Middag</p>
                    <div class="grid grid-cols-3 gap-1.5">
                        @foreach (['13:00', '13:15', '13:30', '14:00', '14:15', '15:00'] as $index => $time)
                            <span class="flex h-9 items-center justify-center rounded-pill border text-[0.6875rem] font-medium tabular-nums {{ $index === 3 ? 'border-primary bg-primary text-white' : 'border-border' }}">{{ $time }}</span>
                        @endforeach
                    </div>
                    <div class="mt-5 flex h-11 items-center justify-center rounded-card bg-primary text-xs font-semibold text-white">Verder met 14:00</div>
                </div>
            </x-platform.phone>
        </div>
    </section>

    {{-- Voor klanten: best beoordeelde kappers (fase 7) --}}
    <section class="mx-auto max-w-5xl px-4 py-12">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold md:text-xl">Top beoordeelde kappers bij jou in de buurt</h2>
                <p class="mt-1 text-sm text-muted">Reviews van klanten die er echt geweest zijn.</p>
            </div>
            <x-ui.button :href="route('directory')" variant="secondary" size="sm" icon="map-pin" wire:navigate>Zoek bij mij in de buurt</x-ui.button>
        </div>
        @if ($topShops->isNotEmpty())
            <div class="mt-6 grid gap-3 md:grid-cols-2">
                @foreach ($topShops as $shop)
                    <x-platform.shop-card :shop="$shop" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- Afsluiter --}}
    <section class="border-t border-border bg-surface">
        <div class="mx-auto max-w-5xl px-4 py-12 text-center">
            <h2 class="text-lg font-bold md:text-xl">Klaar in minder dan drie minuten</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-muted">Meld je zaak aan, kies je diensten en openingstijden, en deel je boekingslink via WhatsApp of Instagram.</p>
            <div class="mt-6 flex justify-center"><x-ui.button :href="route('onboarding')" wire:navigate>Start gratis</x-ui.button></div>
        </div>
    </section>
</div>
