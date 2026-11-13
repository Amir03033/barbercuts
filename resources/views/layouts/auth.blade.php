{{-- Inloggen, registreren en wachtwoord herstellen: op desktop een merkpaneel naast het formulier, op mobiel alleen het formulier. --}}
<!DOCTYPE html>
<html lang="nl">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-dvh bg-bg font-sans text-text">
    <div class="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
        {{-- Merkpaneel --}}
        <aside class="relative hidden overflow-hidden border-r border-border bg-card lg:flex lg:flex-col lg:justify-between lg:p-12">
            <x-brand :href="route('home')" class="relative" />

            <div class="relative max-w-md">
                                <p class="text-4xl leading-[1.1] font-bold tracking-tight text-text">Rust in je agenda.<br>Overzicht in je omzet.</p>
                <p class="mt-4 text-base text-muted">Klanten boeken zelf, elke kapper heeft een eigen agenda en jij ziet in één oogopslag hoe je zaak ervoor staat.</p>
            </div>

            <ul class="relative space-y-3 text-sm text-muted">
                @foreach (['Een eigen boekingslink voor je zaak', 'Agenda per kapper, ook op je telefoon', 'Omzet per dag, week en maand'] as $line)
                    <li class="flex items-center gap-3"><span class="size-1.5 rounded-full bg-gold"></span>{{ $line }}</li>
                @endforeach
            </ul>
        </aside>

        {{-- Formulier --}}
        <div class="flex flex-col">
            <header class="flex items-center justify-between px-4 pt-safe lg:hidden">
                <x-brand :href="route('home')" />
                <x-ui.button :href="route('pricing')" variant="ghost" size="sm" wire:navigate>Prijzen</x-ui.button>
            </header>
            <main class="flex flex-1 items-center justify-center px-4 py-8 lg:px-12">
                <div class="w-full max-w-sm">{{ $slot }}</div>
            </main>
            <footer class="px-4 pb-6 text-center text-xs text-muted pb-safe">
                <a href="{{ route('privacy') }}" class="inline-flex min-h-11 items-center px-2">Privacy</a>
                <a href="{{ route('terms') }}" class="inline-flex min-h-11 items-center px-2">Voorwaarden</a>
                <a href="{{ route('contact') }}" class="inline-flex min-h-11 items-center px-2">Contact</a>
            </footer>
        </div>
    </div>

    <x-ui.toast />
    @livewireScripts
</body>
</html>
