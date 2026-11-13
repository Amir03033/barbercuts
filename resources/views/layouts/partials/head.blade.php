<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ $themeColor ?? '#f5f5f7' }}">
<title>{{ isset($title) ? $title.' · BarberCuts' : 'BarberCuts — afspraken, agenda en omzet voor je kapperszaak' }}</title>
@php
    // Wat je ziet als de pagina gedeeld wordt. Een zaak geeft haar eigen titel, tekst en afbeelding mee ($og).
    $og = array_merge([
        'title' => isset($title) ? $title.' · BarberCuts' : 'BarberCuts — je hele kapperszaak in je broekzak',
        'description' => $description ?? 'Afspraken, agenda en omzet voor je kapperszaak, in één app. Klanten boeken zelf, jij knipt.',
        'image' => url('/og.png'),
        'url' => url()->current(),
    ], $og ?? []);
@endphp
<meta name="description" content="{{ $og['description'] }}">
<meta property="og:site_name" content="BarberCuts">
<meta property="og:type" content="website">
<meta property="og:locale" content="nl_NL">
<meta property="og:title" content="{{ $og['title'] }}">
<meta property="og:description" content="{{ $og['description'] }}">
<meta property="og:url" content="{{ $og['url'] }}">
<meta property="og:image" content="{{ $og['image'] }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $og['title'] }}">
<meta name="twitter:description" content="{{ $og['description'] }}">
<meta name="twitter:image" content="{{ $og['image'] }}">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="BarberCuts">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
</script>
@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
