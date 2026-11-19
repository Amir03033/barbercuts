{{--
    Foutpagina's. Bewust los van de rest van de app: geen database, sessie, Livewire of Vite nodig, zodat ze ook
    werken als de server of database haperen. Alleen inline CSS en het beeldmerk.
--}}
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>{{ $heading }} · BarberCuts</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center; padding: 24px;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; color: #111114; background: #fff; text-align: center; }
        main { width: 100%; max-width: 24rem; }
        .brand { display: inline-flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 600; letter-spacing: -0.01em; color: #111114; text-decoration: none; margin-bottom: 40px; }
        .code { font-size: 64px; line-height: 1; font-weight: 700; letter-spacing: -0.03em; color: #d4d4da; margin: 0 0 16px; }
        h1 { font-size: 22px; line-height: 28px; margin: 0 0 8px; }
        p { font-size: 15px; line-height: 22px; color: #6b6b76; margin: 0 0 28px; }
        .actions { display: flex; flex-direction: column; gap: 10px; }
        .button { display: flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 20px; border: 0; border-radius: 12px;
            background: #111114; color: #fff; font: inherit; font-size: 17px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .button.ghost { background: transparent; color: #111114; border: 1px solid #e4e4e8; }
    </style>
</head>
<body>
    <main>
        <a class="brand" href="{{ url('/') }}" aria-label="BarberCuts">
            <svg viewBox="0 0 100 100" width="32" height="32" aria-hidden="true">
                <rect width="100" height="100" rx="22" fill="#000"/>
                <rect x="30" y="20" width="12" height="59" rx="6" fill="#fff"/>
                <circle cx="50" cy="60" r="13" fill="none" stroke="#fff" stroke-width="12"/>
            </svg>
            <span>barbercuts</span>
        </a>

        @isset($code)
            <p class="code" aria-hidden="true">{{ $code }}</p>
        @endisset
        <h1>{{ $heading }}</h1>
        <p>{{ $message }}</p>

        <div class="actions">
            @if ($retry ?? false)
                <button type="button" class="button" onclick="location.reload()">Opnieuw proberen</button>
                <a class="button ghost" href="{{ url('/') }}">Naar de voorpagina</a>
            @else
                <a class="button" href="{{ url('/') }}">Naar de voorpagina</a>
                <a class="button ghost" href="{{ url('/kappers') }}">Kappers zoeken</a>
            @endif
        </div>
    </main>
</body>
</html>
