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
        <li><strong>Reviews:</strong> het aantal sterren en je tekst, na een bezoek. Op de pagina van de zaak staat je review met je voornaam en de eerste letter van je achternaam.</li>
        <li><strong>Spaarkaart en wachtlijst:</strong> het aantal stempels dat je bij een zaak hebt gespaard, en voor welke dag je op de wachtlijst staat.</li>
        <li><strong>Je keuze voor berichten:</strong> of je berichten van een zaak wilt ontvangen (zoals een herinnering dat het weer tijd is voor een afspraak).</li>
        <li><strong>Locatie bij het zoeken naar een kapper:</strong> de postcode of plaats die je intypt, of de locatie die je deelt. Die gebruiken we alleen voor die ene zoekvraag en slaan we niet op.</li>
        <li><strong>Technische gegevens:</strong> IP-adres en gegevens over je apparaat, om de dienst te beveiligen en storingen op te lossen.</li>
    </ul>

    <h2>Waarom en op welke grondslag</h2>
    <ul>
        <li>Een account en de dienst leveren: uitvoering van de overeenkomst.</li>
        <li>Afspraken bevestigen en herinneren: uitvoering van de overeenkomst met de zaak en de klant.</li>
        <li>Betalingen en facturen: uitvoering van de overeenkomst en wettelijke bewaarplicht.</li>
        <li>Beveiliging, misbruik tegengaan en de dienst verbeteren: gerechtvaardigd belang.</li>
        <li>Een verzoek om een review na je bezoek: gerechtvaardigd belang van de zaak. Je krijgt zo'n verzoek hooguit één keer per 30 dagen.</li>
        <li>Berichten van een zaak die niet over een afspraak gaan (bijvoorbeeld "het is weer tijd voor een knipbeurt"): alleen met je toestemming. In elk bericht staat een link om je af te melden.</li>
        <li>Een bericht als er een plek vrijkomt op de wachtlijst: omdat je je daarvoor zelf hebt aangemeld.</li>
        <li>Pushmeldingen: alleen met je toestemming; je kunt die altijd intrekken.</li>
        <li>Je locatie bij het zoeken naar een kapper: alleen als je die zelf deelt of intypt, en alleen voor die zoekvraag.</li>
    </ul>

    <h2>Reviews</h2>
    <p>Een review kun je alleen achterlaten na een afspraak die echt heeft plaatsgevonden. Reviews zijn openbaar. De zaak kan reageren en een review bij ons melden, maar niet zelf verwijderen. Wil je je review laten weghalen? Stuur ons een bericht.</p>

    <h2>Cookies</h2>
    <p>We gebruiken alleen functionele cookies: om je ingelogd te houden en je formulieren te beveiligen. We gebruiken geen tracking- of advertentiecookies. Je browser onthoudt daarnaast een paar voorkeuren op je eigen apparaat (bijvoorbeeld of het menu is ingeklapt) en bewaart onderdelen van de app zodat die sneller laadt.</p>

    <h2>Met wie delen we gegevens</h2>
    <ul>
        <li>Onze hostingpartij, die de servers levert.</li>
        <li>Mollie, voor het afhandelen van betalingen van abonnementen.</li>
        <li>Onze e-mailprovider, voor het versturen van bevestigingen en herinneringen.</li>
        <li>Een sms- of WhatsApp-provider, alleen als de zaak berichten via die kanalen heeft aangezet.</li>
        <li>De pushdienst van je browser (bijvoorbeeld van Apple, Google of Mozilla), alleen als je pushmeldingen hebt aangezet.</li>
        <li>PDOK, de locatiedienst van de Nederlandse overheid: het adres van een zaak en de postcode of plaats die je intypt bij het zoeken, om die om te zetten naar een plek op de kaart. Daar gaat geen naam of ander gegeven van jou mee.</li>
    </ul>
    <p>Met deze partijen hebben we verwerkersafspraken gemaakt waar dat nodig is. We verkopen geen gegevens.</p>

    <h2>Hoe lang bewaren we gegevens</h2>
    <p>Zolang een zaak of account actief is. Daarna verwijderen of anonimiseren we gegevens binnen 90 dagen, behalve als een wettelijke bewaarplicht geldt (bijvoorbeeld voor facturen).</p>

    <p>Een demozaak die we gebruiken om BarberCuts te laten zien bevat alleen verzonnen gegevens en wordt na 24 uur gewist.</p>

    <h2>Beveiliging</h2>
    <p>Verbindingen zijn versleuteld, wachtwoorden worden niet leesbaar opgeslagen en elke zaak ziet uitsluitend de eigen gegevens. Toegang van onze beheerders is beveiligd met twee-staps-verificatie en wordt gelogd.</p>

    <h2>Jouw rechten</h2>
    <p>Je hebt het recht op inzage, correctie, verwijdering, beperking, bezwaar en overdracht van je gegevens. Een klantaccount kun je zelf verwijderen. Stuur een verzoek naar <a href="mailto:{{ config('barbercuts.company.email') }}">{{ config('barbercuts.company.email') }}</a>; we reageren binnen een maand. Je kunt ook een klacht indienen bij de Autoriteit Persoonsgegevens.</p>

    <h2>Contact</h2>
    <p>{{ config('barbercuts.company.name') }} · <a href="mailto:{{ config('barbercuts.company.email') }}">{{ config('barbercuts.company.email') }}</a></p>
</x-legal.page>
