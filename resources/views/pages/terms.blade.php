{{-- Concept: laat deze tekst juridisch controleren voordat je klanten ermee laat werken. --}}
<x-legal.page title="Algemene voorwaarden">
    <p>Deze voorwaarden gelden voor het gebruik van BarberCuts, aangeboden door {{ config('barbercuts.company.legal_name') }} ("BarberCuts"), door kapperszaken ("de zaak"). Voor klanten die bij een zaak boeken geldt artikel 9.</p>

    <h2>1. De dienst</h2>
    <p>BarberCuts is een online dienst voor het beheren van afspraken, agenda's, klanten en omzet, met een boekingspagina voor de klanten van de zaak. We doen ons best de dienst beschikbaar te houden, maar kunnen geen ononderbroken beschikbaarheid garanderen. Onderhoud kan kort tot onderbreking leiden.</p>

    <h2>2. Account</h2>
    <p>De zaak is verantwoordelijk voor de juistheid van de gegevens, voor het geheim houden van inloggegevens en voor alles wat onder het account gebeurt, ook door kappers die de eigenaar heeft toegevoegd.</p>

    <h2>3. Prijs, proefperiode en betalen</h2>
    <ul>
        <li>De actuele prijs en proefperiode staan op de pagina <a href="{{ route('pricing') }}">Prijzen</a>. Tijdens een gratis lancering of gratis periode betaalt de zaak niets.</li>
        <li>Betalen gaat met iDEAL en een automatische maandelijkse incasso via Mollie. De zaak geeft daarvoor toestemming bij het starten van het abonnement.</li>
        <li>Wijzigen we de prijs, dan melden we dat vooraf. Een lopend abonnement houdt zijn prijs, tenzij we de zaak ruim van tevoren op de hoogte stellen van een nieuwe prijs.</li>
        <li>Zolang betalen via Mollie is uitgeschakeld, staat dat bij Abonnement in de app en betaalt de zaak niets. Gaat betalen aan, dan melden we dat ruim van tevoren.</li>
        <li>Kosten voor sms of WhatsApp gelden alleen als de zaak die kanalen zelf aanzet; de prijs per bericht staat erbij.</li>
        <li>Mislukt een betaling, dan behoudt de zaak nog 7 dagen volledige toegang. Daarna wordt de zaak alleen-lezen en kunnen klanten niet meer online boeken.</li>
    </ul>

    <h2>4. Opzeggen</h2>
    <p>De zaak kan het abonnement op elk moment opzeggen in de app. De toegang blijft tot het einde van de betaalde periode. We verwijderen geen gegevens wanneer een abonnement afloopt.</p>

    <h2>5. Gebruik</h2>
    <p>De zaak gebruikt BarberCuts niet voor onrechtmatige doeleinden, probeert geen gegevens van andere zaken in te zien en verstoort de dienst niet. Bij misbruik kunnen we een zaak blokkeren.</p>

    <h2>6. Reviews en vindbaarheid</h2>
    <ul>
        <li>Klanten kunnen na een voltooide afspraak een review achterlaten. Reviews staan op de pagina van de zaak en tellen mee voor de plek van de zaak in de lijst met kappers.</li>
        <li>De zaak kan op een review reageren en een review bij ons melden. De zaak kan reviews niet zelf verwijderen. Wij kunnen een review verbergen als die beledigend, onwaar of niet van een klant is.</li>
        <li>De zaak beïnvloedt reviews niet met valse afspraken of beloningen voor een goed cijfer. Bij misbruik kunnen we reviews weghalen of de zaak uit de lijst halen.</li>
        <li>Een publieke zaak staat in de lijst met kappers op BarberCuts, met naam, plaats en beoordeling.</li>
    </ul>

    <h2>7. Berichten aan klanten</h2>
    <ul>
        <li>Bevestigingen, herinneringen en een verzoek om een review versturen wij namens de zaak.</li>
        <li>Berichten die niet over een afspraak gaan, zoals "klanten terughalen", gaan alleen naar klanten die daarvoor toestemming hebben gegeven. Zet de zaak die toestemming zelf aan bij een klant, dan staat de zaak ervoor in dat de klant daarmee heeft ingestemd.</li>
        <li>Een klant die zich afmeldt, krijgt die berichten niet meer.</li>
    </ul>

    <h2>8. Spaarkaart, wachtlijst en banner</h2>
    <p>De zaak bepaalt zelf de spaarkaart, de beloning en de tekst en afbeeldingen op haar pagina, en is daarvoor verantwoordelijk. De zaak komt een beloning na die een klant volgens de spaarkaart heeft verdiend. Een plek op de wachtlijst geeft voorrang op een vrijgekomen plek, maar is geen reservering. De zaak plaatst alleen foto's en teksten waarvoor zij de rechten heeft.</p>

    <h2>9. Klanten die boeken</h2>
    <p>Boeken via BarberCuts is voor klanten gratis. De afspraak, de prijs en de behandeling zijn een zaak tussen de klant en de kapperszaak; BarberCuts is daarbij geen partij. Annuleren en verplaatsen kan via de link bij de afspraak, binnen de termijn die de zaak heeft ingesteld. Hoe we met gegevens van klanten omgaan staat in de <a href="{{ route('privacy') }}">privacyverklaring</a>.</p>

    <h2>10. Gegevens</h2>
    <p>De gegevens van de zaak en haar klanten blijven van de zaak. Voor de verwerking van gegevens van klanten gelden de <a href="{{ route('processor-agreement') }}">verwerkersovereenkomst</a> en de <a href="{{ route('privacy') }}">privacyverklaring</a>. De zaak mag haar gegevens laten exporteren of verwijderen via <a href="mailto:{{ config('barbercuts.company.email') }}">{{ config('barbercuts.company.email') }}</a>.</p>

    <h2>11. Aansprakelijkheid</h2>
    <p>Onze aansprakelijkheid is beperkt tot directe schade en tot maximaal het bedrag dat de zaak in de 12 maanden voor het schadeveroorzakende feit aan ons heeft betaald, tenzij sprake is van opzet of bewuste roekeloosheid. We zijn niet aansprakelijk voor gemiste afspraken of omzet door storingen, verkeerd ingevoerde gegevens of het niet bereiken van berichten.</p>

    <h2>12. Wijzigingen en slotbepalingen</h2>
    <p>We kunnen deze voorwaarden wijzigen en melden dat vooraf. Op deze voorwaarden is Nederlands recht van toepassing; geschillen leggen we voor aan de bevoegde rechter in Nederland.</p>

    <h2>Contact</h2>
    <p>{{ config('barbercuts.company.legal_name') }} · <a href="mailto:{{ config('barbercuts.company.email') }}">{{ config('barbercuts.company.email') }}</a></p>
</x-legal.page>
