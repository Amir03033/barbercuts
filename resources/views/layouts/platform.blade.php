{{-- Platform: landingspagina, auth, aanmelden, klantaccount. Altijd licht (B2). --}}
<!DOCTYPE html>
<html lang="nl">
<head>
    @include('layouts.partials.head')
</head>
<body class="flex min-h-dvh flex-col bg-bg font-sans text-text">
    <header class="pt-safe">
        <div class="mx-auto flex min-h-14 max-w-5xl items-center justify-between px-4">
            <x-brand :href="route('home')" />
            <nav aria-label="Account" class="flex items-center gap-1">
                @auth
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
        <div class="mx-auto max-w-5xl px-4 py-6 text-xs text-muted">
            © {{ date('Y') }} BarberCuts
        </div>
    </footer>

    <x-ui.toast />
    @livewireScripts
</body>
</html>
