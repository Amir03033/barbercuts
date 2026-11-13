{{-- Platform: landingspagina, auth, aanmelden, klantaccount en de statische pagina's. Altijd licht (B2). --}}
<!DOCTYPE html>
<html lang="nl">
<head>
    @include('layouts.partials.head')
</head>
<body class="flex min-h-dvh flex-col bg-bg font-sans text-text">
    <header class="sticky top-0 z-30 border-b border-border/70 bg-bg/80 pt-safe backdrop-blur-xl">
        <div class="mx-auto flex min-h-14 max-w-5xl items-center justify-between gap-2 px-4">
            <x-brand :href="route('home')" class="shrink-0" />
            <nav aria-label="Hoofdmenu" class="flex min-w-0 items-center gap-1">
                <x-ui.button :href="route('directory')" variant="ghost" size="sm" wire:navigate class="max-sm:hidden!">Kapper zoeken</x-ui.button>
                <x-ui.button :href="route('pricing')" variant="ghost" size="sm" wire:navigate class="max-sm:hidden!">Prijzen</x-ui.button>
                @auth
                    <x-ui.button variant="ghost" size="sm" x-data x-on:click="$dispatch('open-sheet', 'account-switch')">Account</x-ui.button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.button type="submit" variant="ghost" size="sm">Uitloggen</x-ui.button>
                    </form>
                @else
                    @unless (request()->routeIs('login'))
                        <x-ui.button :href="route('login')" variant="ghost" size="sm" wire:navigate>Inloggen</x-ui.button>
                    @endunless
                @endauth
            </nav>
        </div>
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="border-t border-border pb-safe">
        <div class="mx-auto flex max-w-5xl flex-col gap-3 px-4 py-6 text-xs text-muted sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ date('Y') }} {{ config('barbercuts.company.name') }} · <x-credit /></p>
            <nav aria-label="Voetnavigatie" class="flex flex-wrap gap-x-4 gap-y-1">
                <a href="{{ route('directory') }}" class="inline-flex min-h-11 items-center">Kapper zoeken</a>
                <a href="{{ route('pricing') }}" class="inline-flex min-h-11 items-center">Prijzen</a>
                <a href="{{ route('privacy') }}" class="inline-flex min-h-11 items-center">Privacy</a>
                <a href="{{ route('terms') }}" class="inline-flex min-h-11 items-center">Voorwaarden</a>
                <a href="{{ route('processor-agreement') }}" class="inline-flex min-h-11 items-center">Verwerkersovereenkomst</a>
                <a href="{{ route('contact') }}" class="inline-flex min-h-11 items-center">Contact</a>
            </nav>
        </div>
    </footer>

    <x-account-switcher />
    <x-ui.toast />
    @livewireScripts
</body>
</html>
