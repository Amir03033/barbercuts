{{-- Concept: laat deze tekst juridisch controleren voordat je klanten ermee laat werken. --}}
<x-legal.page title="Privacyverklaring">
    <p>In deze verklaring leggen we uit welke persoonsgegevens {{ config('barbercuts.company.name') }} ("BarberCuts", "wij") verwerkt, waarom, en welke rechten je hebt. BarberCuts is een platform waarmee kapperszaken hun afspraken, agenda en omzet beheren en klanten online een afspraak maken.</p>

    <h2>Twee rollen</h2>
    <ul>
        <li><strong>Wij zijn verwerkingsverantwoordelijke</strong> voor de gegevens van kapperszaken en hun medewerkers (account, abonnement) en van bezoekers van onze website.</li>
        <li><strong>Wij zijn verwerker</strong> voor de gegevens van klanten die een afspraak maken bij een kapperszaak. De kapperszaak bepaalt waarom en hoe die gegevens worden gebruikt en is daarvoor verwerkingsverantwoordelijke. Onze afspraken daarover staan in de <a href="{{ route('processor-agreement') }}">verwerkersovereenkomst</a>. Heb je als klant een vraag over je gegevens bij een zaak? Neem dan eerst contact op met die zaak.</li>
    </ul>

    <h2>Welke gegevens</h2>
    <ul>
        <li><strong>Kapperszaken en medewerkers:</strong> naam, e-mailadres, telefoonnummer, wachtwoord (versleuteld opgeslagen), gegevens van de zaak, abonnement en betalingen.</li>
        <li><strong>Klanten van een zaak:</strong> naam, telefoonnummer, optioneel e-mailadres, afspraken, notities van de kapper en, als de klant een account maakt, accountgegevens.</li>
        <li><strong>Technische gegevens:</strong> IP-adres en gegevens over je apparaat, om de dienst te beveiligen en storingen op te lossen.</li>
    </ul>

    <h2>Waarom en op welke grondslag</h2>
    <ul>
        <li>Een account en de dienst leveren: uitvoering van de overeenkomst.</li>
        <li>Afspraken bevestigen en herinneren: uitvoering van de overeenkomst met de zaak en de klant.</li>
        <li>Betalingen en facturen: uitvoering van de overeenkomst en wettelijke bewaarplicht.</li>
        <li>Beveiliging, misbruik tegengaan en de dienst verbeteren: gerechtvaardigd belang.</li>
        <li>Pushmeldingen: alleen met je toestemming; je kunt die altijd intrekken.</li>
    </ul>

    <h2>Cookies</h2>
    <p>We gebruiken alleen functionele cookies: om je ingelogd te houden en je formulieren te beveiligen. We gebruiken geen tracking- of advertentiecookies.</p>

    <h2>Met wie delen we gegevens</h2>
    <ul>
        <li>Onze hostingpartij, die de servers levert.</li>
        <li>Mollie, voor het afhandelen van betalingen van abonnementen.</li>
        <li>Onze e-mailprovider, voor het versturen van bevestigingen en herinneringen.</li>
    </ul>
    <p>Met deze partijen hebben we verwerkersafspraken gemaakt waar dat nodig is. We verkopen geen gegevens.</p>

    <h2>Hoe lang bewaren we gegevens</h2>
    <p>Zolang een zaak of account actief is. Daarna verwijderen of anonimiseren we gegevens binnen 90 dagen, behalve als een wettelijke bewaarplicht geldt (bijvoorbeeld voor facturen).</p>

    <h2>Beveiliging</h2>
    <p>Verbindingen zijn versleuteld, wachtwoorden worden niet leesbaar opgeslagen en elke zaak ziet uitsluitend de eigen gegevens. Toegang van onze beheerders is beveiligd met twee-staps-verificatie en wordt gelogd.</p>

    <h2>Jouw rechten</h2>
    <p>Je hebt het recht op inzage, correctie, verwijdering, beperking, bezwaar en overdracht van je gegevens. Een klantaccount kun je zelf verwijderen. Stuur een verzoek naar <a href="mailto:{{ config('barbercuts.company.email') }}">{{ config('barbercuts.company.email') }}</a>; we reageren binnen een maand. Je kunt ook een klacht indienen bij de Autoriteit Persoonsgegevens.</p>

    <h2>Contact</h2>
    <p>{{ config('barbercuts.company.name') }} · <a href="mailto:{{ config('barbercuts.company.email') }}">{{ config('barbercuts.company.email') }}</a></p>
</x-legal.page>
