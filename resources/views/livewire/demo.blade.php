<div class="mx-auto max-w-sm px-4 py-8">
    <p class="text-xs font-semibold uppercase tracking-wide text-gold-ink">Demo voor pitchers</p>
    <h1 class="mt-1 text-lg font-semibold text-text">Hoi {{ $pitcher->name }}</h1>
    <p class="mt-2 text-sm text-muted">
        Je krijgt een eigen, gevulde kapperszaak met kappers, klanten, afspraken en omzet. Alles is fictief:
        je kunt overal op tikken en alles wijzigen. Er worden geen echte berichten verstuurd.
    </p>

    <form method="POST" action="{{ route('demo.start') }}" class="mt-6 space-y-3">
        @csrf
        @if ($shop)
            <x-ui.card>
                <p class="text-sm font-semibold text-text">Je demo loopt nog</p>
                <p class="mt-1 text-sm text-muted">{{ $shop->name }} · wordt gewist {{ $shop->demo_expires_at->diffForHumans() }}</p>
            </x-ui.card>
            <x-ui.button :href="route('app.today')" full>Ga verder met je demo</x-ui.button>
            <x-ui.button type="submit" variant="secondary" full>Opnieuw beginnen</x-ui.button>
        @else
            <x-ui.button type="submit" full>Start demo</x-ui.button>
        @endif
    </form>

    <x-ui.card class="mt-8">
        <p class="text-sm font-semibold text-text">Wat je kunt laten zien</p>
        <ul class="mt-2 space-y-2 text-sm text-muted">
            <li>Vandaag: de dag in één oogopslag en een afspraak voltooien in drie tikken.</li>
            <li>Nieuwe afspraak: een telefonische boeking in een paar seconden.</li>
            <li>Omzet: per dag, week en maand, en per kapper.</li>
            <li>"Bekijk als klant": zo boekt een klant online, op de pagina van de zaak.</li>
        </ul>
        <p class="mt-3 text-xs text-muted">Een demo blijft {{ $hours }} uur staan en wordt daarna gewist.</p>
    </x-ui.card>

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="min-h-11 text-sm text-muted underline">Uitloggen</button>
    </form>
</div>
