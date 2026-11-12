<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ $themeColor ?? '#f7f6f3' }}">
<title>{{ isset($title) ? $title.' · BarberCuts' : 'BarberCuts — afspraken, agenda en omzet voor je kapperszaak' }}</title>
@isset($description)
    <meta name="description" content="{{ $description }}">
@endisset
<link rel="manifest" href="/manifest.webmanifest">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
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
