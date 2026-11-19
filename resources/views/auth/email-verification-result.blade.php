{{--
    Uitkomst van de link in de bevestigingsmail, voor wie de link opent in een andere browser of app (of niet ingelogd is).
    Bij "bevestigd" krijgen andere open schermen in deze browser een seintje (BroadcastChannel), zodat ze meteen verdergaan.
--}}
@php
    $copy = match ($state) {
        'verified' => ['icon' => 'check', 'tone' => 'bg-completed/10 text-completed', 'title' => 'Je e-mailadres is bevestigd'],
        'expired' => ['icon' => 'clock', 'tone' => 'bg-gold-soft text-gold-ink', 'title' => 'Deze link is verlopen'],
        default => ['icon' => 'alert', 'tone' => 'bg-red-50 text-danger', 'title' => 'Deze link werkt niet'],
    };
@endphp

<x-layouts::auth :title="$copy['title']">
    @if ($state === 'verified')
        <x-email-verified-signal />
    @endif

    <div class="mx-auto max-w-sm px-4 py-8 text-center lg:py-0">
        <span class="animate-pop-in mx-auto flex size-16 items-center justify-center rounded-full {{ $copy['tone'] }}">
            <x-ui.icon :name="$copy['icon']" size="28" />
        </span>
        <h1 class="rise-in mt-5 text-[1.75rem] leading-9 font-bold tracking-tight text-text">{{ $copy['title'] }}</h1>

        @if ($state === 'verified')
            @if ($email)
                <p class="rise-in mt-2 text-base text-muted" style="--rise-delay: 60ms"><strong class="font-semibold text-text">{{ $email }}</strong> is bevestigd.</p>
            @endif

            @if ($viewer && ! $sameUser)
                <p class="rise-in mt-3 text-sm text-muted" style="--rise-delay: 120ms">Je bent hier ingelogd met een ander account ({{ $viewer->email }}). Wissel van account om verder te gaan met {{ $email }}.</p>
                <form method="POST" action="{{ route('logout', ['switch' => 1]) }}" class="mt-7">
                    @csrf
                    <x-ui.button type="submit" full>Inloggen als {{ $email }}</x-ui.button>
                </form>
                <x-ui.button :href="$viewer->homeRoute()" variant="ghost" full class="mt-2">Blijf ingelogd als {{ $viewer->name }}</x-ui.button>
            @elseif ($viewer && $sameUser)
                <x-ui.button :href="$viewer->homeRoute()" full class="mt-7">Verder</x-ui.button>
            @else
                <div class="rise-in mt-5 rounded-card bg-surface p-4 text-left text-sm text-muted" style="--rise-delay: 120ms">
                    <p class="flex gap-2.5"><x-ui.icon name="check" size="16" class="mt-0.5 shrink-0 text-completed" />Heb je BarberCuts nog open op je telefoon of in een ander tabblad? Dat gaat vanzelf verder. Je kunt dit scherm sluiten.</p>
                </div>
                <x-ui.button :href="route('login', array_filter(['email' => $email]))" full class="mt-6">Hier inloggen</x-ui.button>
            @endif
        @else
            <p class="rise-in mt-2 text-base text-muted" style="--rise-delay: 60ms">
                @if ($state === 'expired')
                    Een link in de bevestigingsmail is {{ $hours }} uur geldig. Vraag een nieuwe aan, dat duurt een paar seconden.
                @else
                    Misschien heb je je e-mailadres daarna gewijzigd, of is de link niet helemaal meegekopieerd. Vraag een nieuwe link aan.
                @endif
            </p>

            @if ($viewer)
                <x-ui.button :href="route('verification.notice')" full class="mt-7">Stuur een nieuwe link</x-ui.button>
            @else
                <x-ui.button :href="route('login', array_filter(['email' => $email]))" full class="mt-7">Inloggen voor een nieuwe link</x-ui.button>
                <p class="mt-3 text-xs text-muted">Na het inloggen tik je op "Stuur de link opnieuw".</p>
            @endif
        @endif
    </div>
</x-layouts::auth>
