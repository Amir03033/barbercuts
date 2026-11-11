{{--
    Publieke pagina's van een zaak (/{slug}): in het thema van de zaak (§7.9).
    Verwacht `$shop` (vanaf fase 1) of losse `$themeMode`, `$accentColor`, `$onAccentColor`.
--}}
@php
    $themeMode = isset($shop) ? $shop->theme_mode->value : ($themeMode ?? 'light');
    $accentColor = isset($shop) ? $shop->accent_color : ($accentColor ?? '#111827');
    $onAccentColor = isset($shop) ? $shop->onAccentColor() : ($onAccentColor ?? '#ffffff');
    $themeMode = in_array($themeMode, ['light', 'dark'], true) ? $themeMode : 'light';
    $accentColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $accentColor) ? $accentColor : '#111827';
    $onAccentColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $onAccentColor) ? $onAccentColor : '#ffffff';
    $themeColor = $themeMode === 'dark' ? '#0f0f12' : '#ffffff';
@endphp
<!DOCTYPE html>
<html lang="nl" data-theme="{{ $themeMode }}" style="--accent: {{ $accentColor }}; --on-accent: {{ $onAccentColor }}">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-dvh bg-bg font-sans text-text">
    <main class="mx-auto max-w-2xl">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-2xl px-4 py-8 pb-safe text-center text-xs text-muted">
        Online boeken via <x-brand :href="route('home')" size="sm" class="!text-xs !text-muted align-middle" />
    </footer>

    <x-ui.toast />
    @livewireScripts
</body>
</html>
