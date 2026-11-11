<x-layouts::platform title="Prijzen">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-lg font-bold md:text-xl">Eén prijs, alles erbij</h1>
        <p class="mt-1 text-sm text-muted">Geen opstartkosten, geen kosten per kapper of per afspraak.</p>

        <x-ui.card class="mt-6 space-y-4">
            <div>
                <p class="text-xs font-medium text-muted">{{ $plan->name ?? 'BarberCuts' }}</p>
                <p class="text-display font-bold tracking-tight tabular-nums">{{ money($plan->price_cents ?? 1000) }} <span class="text-base font-medium text-muted">per maand</span></p>
                <p class="mt-1 text-sm text-muted">
                    @if ($billing)
                        De eerste {{ $plan->trial_days ?? 14 }} dagen gratis. Daarna maandelijks opzegbaar.
                    @else
                        Tijdens de lancering gratis. Je hoort het ruim van tevoren voordat daar verandering in komt.
                    @endif
                </p>
            </div>
            <ul class="space-y-2 text-sm">
                @foreach (['Eigen boekingspagina met je logo en kleuren', 'Onbeperkt kappers, elk met een eigen agenda', 'Onbeperkt afspraken en klanten', 'Omzet per dag, week, maand en jaar', 'Herinneringen aan klanten', 'App op je telefoon, zonder installatie uit een winkel'] as $feature)
                    <li class="flex gap-2"><x-ui.icon name="check" size="20" class="text-completed" /><span>{{ $feature }}</span></li>
                @endforeach
            </ul>
            <x-ui.button :href="route('onboarding')" full wire:navigate>Start gratis</x-ui.button>
        </x-ui.card>

        <h2 class="mt-10 text-base font-semibold">Veelgestelde vragen</h2>
        <dl class="mt-3 space-y-4 text-sm">
            <div><dt class="font-medium">Moet ik een creditcard invullen om te beginnen?</dt><dd class="text-muted">Nee. Je meldt je aan en kunt direct aan de slag.</dd></div>
            <div><dt class="font-medium">Hoe betaal ik?</dt><dd class="text-muted">Met iDEAL en een automatische maandelijkse incasso via Mollie. Opzeggen kan altijd; je kunt werken tot het einde van de periode die je betaald hebt.</dd></div>
            <div><dt class="font-medium">Wat gebeurt er met mijn gegevens als ik stop?</dt><dd class="text-muted">We verwijderen niets zomaar. Je kunt je gegevens laten verwijderen via <a class="underline" href="{{ route('contact') }}">contact</a>.</dd></div>
        </dl>
    </div>
</x-layouts::platform>
