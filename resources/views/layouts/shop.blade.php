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
    $themeColor = $themeMode === 'dark' ? '#000000' : '#f5f5f7';

    // Delen van de link: een eigen afbeelding met de naam, kleur en beoordeling van de zaak.
    if (isset($shop) && $shop->exists) {
        $og = [
            'title' => $shop->name.' · online een afspraak maken',
            'description' => collect([
                $shop->hasRating() ? number_format((float) $shop->rating_avg, 1, ',', '').' van 5 sterren ('.$shop->rating_count.' reviews)' : null,
                $shop->city ? 'Barbershop in '.$shop->city : null,
                'Boek in een halve minuut, ook zonder account.',
            ])->filter()->implode(' · '),
            'image' => route('shop.share', ['slug' => $shop->slug, 'v' => substr(sha1($shop->updated_at.'|'.$shop->rating_count), 0, 8)]),
            'url' => $shop->bookingUrl(),
        ];
    }
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
        <p class="mt-1"><x-credit /></p>
    </footer>

    <x-ui.toast />
    @livewireScripts
</body>
</html>
