{{--
    Kapper-app (/app): licht. Mobiel en tablet: bottom navigation. Desktop (lg): zijbalk.
    Layout-opties: active (voor de bottom nav), wide (true = brede inhoud voor dashboard, agenda en lijsten).
--}}
<!DOCTYPE html>
<html lang="nl">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-dvh bg-bg font-sans text-text"
    x-data="{ collapsed: (() => { try { return localStorage.getItem('bc-sidebar') === '1'; } catch (e) { return false; } })() }"
    x-effect="(() => { try { localStorage.setItem('bc-sidebar', collapsed ? '1' : '0'); } catch (e) {} })()">
    @isset($currentShop)
        <x-app.sidebar :shop="$currentShop" :is-owner="auth()->user()?->isOwnerOf($currentShop) ?? false" />
    @endisset

    <div class="transition-[padding] duration-300 ease-out-soft" :class="collapsed ? 'lg:pl-[4.5rem]' : 'lg:pl-60'">
        <x-app.impersonation-banner />
        @isset($currentShop)
            <x-app.demo-banner :shop="$currentShop" />
            <x-app.access-banner :shop="$currentShop" :is-owner="auth()->user()?->isOwnerOf($currentShop) ?? false" />
        @endisset

        <main @class([
            'mx-auto w-full max-w-2xl pb-[calc(var(--spacing-nav)+env(safe-area-inset-bottom)+1rem)] lg:pb-16',
            'md:max-w-3xl lg:max-w-6xl lg:px-4' => $wide ?? false,
            'md:max-w-3xl lg:max-w-3xl lg:px-4' => ! ($wide ?? false),
        ])>
            {{ $slot }}
        </main>
    </div>

    <x-ui.bottom-nav :active="$active ?? null" />
    <x-account-switcher />
    <x-ui.toast nav />
    @livewireScripts
</body>
</html>
