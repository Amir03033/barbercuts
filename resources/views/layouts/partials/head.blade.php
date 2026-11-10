<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ $themeColor ?? '#ffffff' }}">
<title>{{ isset($title) ? $title.' · BarberCuts' : 'BarberCuts — afspraken, agenda en omzet voor je kapperszaak' }}</title>
@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
