{{-- Basis voor de juridische pagina's: titel, datum, wie de aanbieder is en leesbare tekst zonder losse styling per pagina. --}}
@props(['title', 'updated' => '13 november 2026'])

<x-layouts::platform :title="$title">
    <article class="mx-auto max-w-2xl px-4 py-10 md:py-16">
        <h1 class="text-[1.75rem] leading-9 font-bold tracking-tight md:text-4xl">{{ $title }}</h1>
        <p class="mt-2 text-sm text-muted">Laatst bijgewerkt: {{ $updated }}</p>

        <div class="mt-6 rounded-card border border-border/70 bg-card p-4 text-sm">
            <p class="font-semibold">BarberCuts is een dienst van {{ config('barbercuts.company.legal_name') }}</p>
            <p class="mt-1 text-muted">
                @if (config('barbercuts.company.kvk'))KVK {{ config('barbercuts.company.kvk') }} · @endif
                @if (config('barbercuts.company.address')){{ config('barbercuts.company.address') }} · @endif
                <a href="mailto:{{ config('barbercuts.company.email') }}" class="font-medium underline">{{ config('barbercuts.company.email') }}</a>
            </p>
        </div>

        <div class="mt-8 space-y-3 text-base leading-7 [&_a]:font-medium [&_a]:text-link [&_a]:underline [&_h2]:mt-10 [&_h2]:text-xl [&_h2]:font-bold [&_h2]:tracking-tight [&_ol]:list-decimal [&_ol]:space-y-1.5 [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:space-y-1.5 [&_ul]:pl-5">
            {{ $slot }}
        </div>

        <nav aria-label="Andere juridische pagina's" class="mt-12 flex flex-wrap gap-2 border-t border-border pt-6">
            @foreach (['privacy' => 'Privacy', 'terms' => 'Voorwaarden', 'processor-agreement' => 'Verwerkersovereenkomst', 'contact' => 'Contact'] as $route => $label)
                <x-ui.chip :href="route($route)" :active="request()->routeIs($route)">{{ $label }}</x-ui.chip>
            @endforeach
        </nav>
    </article>
</x-layouts::platform>
