{{--
    Printbare flyer voor pitchers: A5 met een QR-code naar de eigen aanmeldlink. Losse pagina met alleen inline stijlen,
    zodat hij overal goed afdrukt. Afdrukken of "Bewaar als PDF" via de knop.
--}}
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Flyer · BarberCuts</title>
    <style>
        @page { size: A5; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: -apple-system, 'SF Pro Text', Inter, 'Helvetica Neue', Arial, sans-serif; color: #1d1d1f; background: #e9e9ee; }
        .bar { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 16px; background: rgba(255,255,255,.9); backdrop-filter: blur(12px); border-bottom: 1px solid #e5e5ea; font-size: 14px; }
        .bar a { color: #0071e3; text-decoration: none; font-weight: 600; }
        .bar button { min-height: 44px; padding: 0 18px; border: 0; border-radius: 12px; background: #1d1d1f; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .sheet { width: 148mm; height: 210mm; margin: 16px auto; background: #fff; padding: 12mm 11mm 10mm; display: flex; flex-direction: column; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,.18);
            background-image: radial-gradient(90mm 60mm at 100% 0%, rgba(0,113,227,.14), transparent 70%), radial-gradient(70mm 50mm at 0% 100%, rgba(52,199,89,.12), transparent 70%); }
        .brand { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 15pt; letter-spacing: -.01em; }
        .brand svg { width: 9mm; height: 9mm; }
        h1 { margin-top: 10mm; font-size: 25pt; line-height: 1.06; letter-spacing: -.035em; font-weight: 800; }
        .sub { margin-top: 3mm; font-size: 11pt; color: #6e6e73; }
        ul { list-style: none; margin-top: 7mm; }
        li { display: flex; gap: 3mm; align-items: flex-start; margin-top: 3mm; font-size: 11pt; font-weight: 600; }
        li span.t { flex-shrink: 0; width: 6mm; height: 6mm; border-radius: 50%; background: #248a3d; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 9pt; font-weight: 800; margin-top: .3mm; }
        .price { margin-top: 7mm; display: flex; align-items: center; gap: 4mm; background: #1d1d1f; color: #fff; border-radius: 4mm; padding: 4.5mm 5mm; }
        .price b { font-size: 22pt; letter-spacing: -.03em; line-height: 1; }
        .price span { font-size: 9.5pt; color: rgba(255,255,255,.8); line-height: 1.35; }
        .scan { margin-top: auto; display: grid; grid-template-columns: 38mm 1fr; gap: 5mm; align-items: center; }
        .qr { width: 38mm; height: 38mm; padding: 2.5mm; background: #fff; border-radius: 3.5mm; border: 1px solid #e5e5ea; }
        .qr svg { width: 100%; height: 100%; display: block; }
        .scan h2 { font-size: 14pt; letter-spacing: -.02em; line-height: 1.15; }
        .scan p { margin-top: 1.5mm; font-size: 9.5pt; color: #6e6e73; }
        .scan .url { margin-top: 2mm; font-family: 'SF Mono', Menlo, monospace; font-size: 7.6pt; color: #1d1d1f; word-break: break-all; }
        .who { margin-top: 5mm; padding-top: 3.5mm; border-top: 1px solid #e5e5ea; font-size: 9pt; color: #6e6e73; display: flex; justify-content: space-between; gap: 6px; }
        @media print { body { background: #fff; } .bar { display: none; } .sheet { margin: 0; box-shadow: none; } }
    </style>
</head>
<body>
    <div class="bar">
        <a href="{{ route('pitcher.overview') }}">← Terug</a>
        <span style="color:#6e6e73">A5 · kies bij afdrukken “Bewaar als PDF” of print hem</span>
        <button type="button" onclick="window.print()">Afdrukken</button>
    </div>

    <div class="sheet">
        <div class="brand">
            <svg viewBox="0 0 100 100" aria-hidden="true"><rect width="100" height="100" rx="22" fill="#000"/><rect x="30" y="20" width="12" height="59" rx="6" fill="#fff"/><circle cx="50" cy="60" r="13" fill="none" stroke="#fff" stroke-width="12"/></svg>
            barbercuts
        </div>

        <h1>Je kapperszaak in je broekzak.</h1>
        <p class="sub">Klanten boeken zelf. Jij ziet je dag en je omzet op je telefoon.</p>

        <ul>
            <li><span class="t">✓</span>Klanten boeken online, ook zonder account</li>
            <li><span class="t">✓</span>Een eigen agenda per kapper</li>
            <li><span class="t">✓</span>Omzet per dag, week en maand</li>
            <li><span class="t">✓</span>Herinneringen tegen no-shows</li>
        </ul>

        <div class="price">
            <b>{{ $trialDays }} dagen</b>
            <span><strong>gratis proberen</strong>, geen creditcard.<br>@if ($billing)Daarna {{ $price }} per maand, opzeggen kan altijd.@else Tijdens de lancering gratis.@endif</span>
        </div>

        <div class="scan">
            <div class="qr" role="img" aria-label="QR-code om je zaak aan te melden">{!! $qr !!}</div>
            <div>
                <h2>Scan en meld je zaak aan</h2>
                <p>In 3 minuten staat je boekingspagina online.</p>
                <p class="url">{{ $shortLink }}</p>
            </div>
        </div>

        <div class="who">
            <span>Jouw contact: <strong style="color:#1d1d1f">{{ $pitcher->name }}</strong>@if ($pitcher->phone) · {{ $pitcher->phone }}@endif</span>
            <span>hallo@barbercuts.nl</span>
        </div>
    </div>
</body>
</html>
