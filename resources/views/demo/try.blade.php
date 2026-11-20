{{--
    Openbare demolink (/probeer, /probeer/{code}). Wat iemand ziet die de link van een pitcher krijgt: uitleg en één knop.
    De demo start pas na de tik (POST), zodat linkvoorbeelden van WhatsApp en zoekmachines geen demozaken aanmaken.
--}}
@php
    $pitcherName = $pitcher ? explode(' ', trim($pitcher->name))[0] : null;
    $shopName = $invite?->shop_name ?? \App\Services\Demo\DemoShopService::DEFAULT_NAME;
    $ogTitle = match (true) {
        $invite !== null => "Een demo van BarberCuts voor {$invite->shop_name}",
        $pitcherName !== null => "{$pitcherName} nodigt je uit om BarberCuts te proberen",
        default => 'Probeer BarberCuts — je eigen demozaak',
    };
    $features = [
        ['calendar', 'Agenda per kapper', 'Drie kappers met elk een eigen agenda, pauzes en vrije dagen.'],
        ['plus', 'Afspraak in seconden', 'Zet een telefonische boeking erin met een paar tikken.'],
        ['trending-up', 'Omzet in één oogopslag', 'Per dag, week en maand, en per kapper.'],
        ['store', 'Boeken als klant', 'Bekijk de boekingspagina zoals je klanten hem zien.'],
    ];
@endphp

<x-layouts::platform :title="$invite ? 'Demo voor '.$invite->shop_name : 'Probeer de demo'"
    :og="['title' => $ogTitle, 'description' => 'Geen account nodig. Bekijk in een minuut hoe je agenda, klanten en omzet eruitzien in BarberCuts.', 'url' => url()->current(), 'image' => $shareImage]">
    <section class="relative">
        <div class="hero-glow pointer-events-none absolute inset-0" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-xl px-4 pt-10 pb-8 text-center md:pt-16">
            <p class="rise-in mx-auto inline-flex items-center gap-2 rounded-pill bg-card/80 px-3.5 py-1.5 text-xs font-semibold text-muted shadow-soft ring-1 ring-border backdrop-blur">
                <span class="size-1.5 animate-pulse-dot rounded-full bg-completed"></span>
                Live demo · geen account nodig
            </p>

            <h1 class="rise-in mt-5 text-[2rem] leading-[1.1] font-bold tracking-tight text-balance text-text md:text-5xl" style="--rise-delay: 60ms">
                @if ($invite)
                    Zo ziet <span class="text-link">{{ $invite->shop_name }}</span> eruit in BarberCuts
                @else
                    Probeer BarberCuts in je eigen voorbeeldzaak
                @endif
            </h1>
            <p class="rise-in mx-auto mt-4 max-w-md text-base text-muted md:text-lg" style="--rise-delay: 120ms">
                @if ($invite)
                    Je krijgt een volle agenda met drie kappers en 140 klanten, alvast onder jouw naam. Tik overal op en verander wat je wilt — de klanten zijn verzonnen en er wordt niets verstuurd.
                @else
                    Je krijgt <strong class="font-semibold text-text">{{ $shopName }}</strong>: drie kappers, 140 klanten en een volle agenda. Tik overal op en verander wat je wilt — alles is verzonnen en er wordt niets verstuurd.
                @endif
            </p>

            @if ($pitcherName)
                <div class="rise-in mx-auto mt-6 flex max-w-sm items-center gap-3 rounded-card bg-card p-3 pr-4 text-left shadow-soft ring-1 ring-border/70" style="--rise-delay: 160ms">
                    <x-ui.avatar :name="$pitcher->name" />
                    <p class="min-w-0 text-sm text-muted">
                        @if ($invite)
                            <strong class="font-semibold text-text">{{ $pitcherName }}</strong> heeft deze demo voor je klaargezet. Vragen? Stuur gerust een berichtje terug.
                        @else
                            Je kreeg deze link van <strong class="font-semibold text-text">{{ $pitcherName }}</strong>. Vragen? Stuur gerust een berichtje terug.
                        @endif
                    </p>
                </div>
            @endif

            @if (session('demo_error'))
                <x-ui.alert type="warning" class="mx-auto mt-6 max-w-sm text-left">{{ session('demo_error') }}</x-ui.alert>
            @endif

            <form method="POST" action="{{ route('demo.try.start') }}" class="rise-in mx-auto mt-7 max-w-sm" style="--rise-delay: 200ms"
                x-data="{ busy: false }" x-on:submit="busy = true">
                @csrf
                @if ($code)
                    <input type="hidden" name="code" value="{{ $code }}">
                @endif
                @if ($invite)
                    <input type="hidden" name="invite" value="{{ $invite->token }}">
                @endif
                <button type="submit" x-bind:disabled="busy"
                    class="relative inline-flex min-h-[3.5rem] w-full items-center justify-center gap-2 rounded-input bg-accent px-6 text-lg font-semibold text-on-accent shadow-pop transition-[transform,filter] duration-150 hover:brightness-110 active:scale-[0.98] disabled:opacity-80">
                    <span x-show="! busy" class="inline-flex items-center gap-2">{{ $invite ? 'Bekijk je zaak' : 'Start de demo' }} <x-ui.icon name="arrow-right" size="20" /></span>
                    <span x-show="busy" x-cloak class="inline-flex items-center gap-2"><x-ui.icon name="loader" size="20" class="animate-spin" /> Je zaak wordt klaargezet…</span>
                </button>
                @if ($viewer && ! $viewer->is_demo_visitor)
                    <p class="mt-3 text-xs text-muted">Je bent ingelogd als {{ $viewer->email }}. De demo start in een apart demo-account; je wordt daarvoor uitgelogd.</p>
                @else
                    <p class="mt-3 text-xs text-muted">Klaar in een paar seconden. Werkt het best op je telefoon.</p>
                @endif
            </form>
        </div>
    </section>

    @if ($video)
        <section class="mx-auto max-w-3xl px-4 pb-10">
            <h2 class="mb-4 text-center text-sm font-semibold text-muted">Liever eerst kijken? Zo werkt het</h2>
            <x-promo-video :video="$video" />
        </section>
    @endif

    <section class="mx-auto max-w-3xl px-4 pb-10">
        <h2 class="text-center text-sm font-semibold text-muted">Dit kun je meteen uitproberen</h2>
        <div class="stagger mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($features as [$icon, $title, $text])
                <div class="flex items-start gap-3 rounded-card bg-card p-4 shadow-soft ring-1 ring-border/70">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gold-soft text-gold-ink"><x-ui.icon :name="$icon" size="20" /></span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-text">{{ $title }}</span>
                        <span class="block text-sm text-muted">{{ $text }}</span>
                    </span>
                </div>
            @endforeach
        </div>

        <div class="mt-8 rounded-[1.75rem] bg-surface p-5 text-center md:p-6">
            <p class="text-base font-semibold text-text">Bevalt het?</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-muted">
                Meld je eigen zaak aan in een paar minuten.
                @if ($billing)
                    De eerste {{ $trialDays }} dagen zijn gratis, zonder creditcard.
                @else
                    Tijdens de lancering is BarberCuts gratis, zonder creditcard.
                @endif
            </p>
            <x-ui.button :href="route('onboarding')" variant="secondary" class="mt-4">Meld je zaak aan</x-ui.button>
            <p class="mt-4 text-xs text-muted">De demo blijft {{ $hours }} uur staan en wordt daarna gewist.</p>
        </div>
    </section>
</x-layouts::platform>
