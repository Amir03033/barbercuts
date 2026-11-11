{{-- Concept: laat deze tekst juridisch controleren voordat je klanten ermee laat werken. --}}
<x-legal.page title="Algemene voorwaarden">
    <p>Deze voorwaarden gelden voor het gebruik van BarberCuts, aangeboden door {{ config('barbercuts.company.name') }} ("BarberCuts"), door kapperszaken ("de zaak").</p>

    <h2>1. De dienst</h2>
    <p>BarberCuts is een online dienst voor het beheren van afspraken, agenda's, klanten en omzet, met een boekingspagina voor de klanten van de zaak. We doen ons best de dienst beschikbaar te houden, maar kunnen geen ononderbroken beschikbaarheid garanderen. Onderhoud kan kort tot onderbreking leiden.</p>

    <h2>2. Account</h2>
    <p>De zaak is verantwoordelijk voor de juistheid van de gegevens, voor het geheim houden van inloggegevens en voor alles wat onder het account gebeurt, ook door kappers die de eigenaar heeft toegevoegd.</p>

    <h2>3. Prijs, proefperiode en betalen</h2>
    <ul>
        <li>De actuele prijs en proefperiode staan op de pagina <a href="{{ route('pricing') }}">Prijzen</a>. Tijdens een gratis lancering of gratis periode betaalt de zaak niets.</li>
        <li>Betalen gaat met iDEAL en een automatische maandelijkse incasso via Mollie. De zaak geeft daarvoor toestemming bij het starten van het abonnement.</li>
        <li>Wijzigen we de prijs, dan melden we dat vooraf. Een lopend abonnement houdt zijn prijs, tenzij we de zaak ruim van tevoren op de hoogte stellen van een nieuwe prijs.</li>
        <li>Mislukt een betaling, dan behoudt de zaak nog 7 dagen volledige toegang. Daarna wordt de zaak alleen-lezen en kunnen klanten niet meer online boeken.</li>
    </ul>

    <h2>4. Opzeggen</h2>
    <p>De zaak kan het abonnement op elk moment opzeggen in de app. De toegang blijft tot het einde van de betaalde periode. We verwijderen geen gegevens wanneer een abonnement afloopt.</p>

    <h2>5. Gebruik</h2>
    <p>De zaak gebruikt BarberCuts niet voor onrechtmatige doeleinden, probeert geen gegevens van andere zaken in te zien en verstoort de dienst niet. Bij misbruik kunnen we een zaak blokkeren.</p>

    <h2>6. Gegevens</h2>
    <p>De gegevens van de zaak en haar klanten blijven van de zaak. Voor de verwerking van gegevens van klanten gelden de <a href="{{ route('processor-agreement') }}">verwerkersovereenkomst</a> en de <a href="{{ route('privacy') }}">privacyverklaring</a>. De zaak mag haar gegevens laten exporteren of verwijderen via <a href="mailto:{{ config('barbercuts.company.email') }}">{{ config('barbercuts.company.email') }}</a>.</p>

    <h2>7. Aansprakelijkheid</h2>
    <p>Onze aansprakelijkheid is beperkt tot directe schade en tot maximaal het bedrag dat de zaak in de 12 maanden voor het schadeveroorzakende feit aan ons heeft betaald, tenzij sprake is van opzet of bewuste roekeloosheid. We zijn niet aansprakelijk voor gemiste afspraken of omzet door storingen, verkeerd ingevoerde gegevens of het niet bereiken van berichten.</p>

    <h2>8. Wijzigingen en slotbepalingen</h2>
    <p>We kunnen deze voorwaarden wijzigen en melden dat vooraf. Op deze voorwaarden is Nederlands recht van toepassing; geschillen leggen we voor aan de bevoegde rechter in Nederland.</p>

    <h2>Contact</h2>
    <p>{{ config('barbercuts.company.name') }} · <a href="mailto:{{ config('barbercuts.company.email') }}">{{ config('barbercuts.company.email') }}</a></p>
</x-legal.page>
