{{-- Kapper-app (/app): licht, met bottom navigation. Geef `active` mee voor het actieve menu-item. --}}
<!DOCTYPE html>
<html lang="nl">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-dvh bg-bg font-sans text-text">
    <main class="mx-auto max-w-2xl" style="padding-bottom: calc(var(--spacing-nav) + env(safe-area-inset-bottom) + 1rem)">
        {{ $slot }}
    </main>

    <x-ui.bottom-nav :active="$active ?? null" />
    <x-ui.toast nav />
    @livewireScripts
</body>
</html>
