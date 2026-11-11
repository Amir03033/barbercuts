{{-- Concept: laat deze tekst juridisch controleren voordat je klanten ermee laat werken. --}}
<x-legal.page title="Verwerkersovereenkomst">
    <p>Deze verwerkersovereenkomst hoort bij de <a href="{{ route('terms') }}">algemene voorwaarden</a> en geldt tussen de kapperszaak ("Verwerkingsverantwoordelijke") en {{ config('barbercuts.company.name') }} ("Verwerker"), op grond van artikel 28 van de Algemene verordening gegevensbescherming (AVG). Ze gaat in zodra de zaak BarberCuts gebruikt.</p>

    <h2>1. Onderwerp en doel</h2>
    <p>De Verwerker verwerkt persoonsgegevens van klanten van de Verwerkingsverantwoordelijke uitsluitend om de dienst BarberCuts te leveren: afspraken, agenda, klantenbestand, herinneringen en omzetoverzichten.</p>

    <h2>2. Gegevens en betrokkenen</h2>
    <ul>
        <li><strong>Betrokkenen:</strong> klanten van de zaak en medewerkers.</li>
        <li><strong>Gegevens:</strong> naam, telefoonnummer, e-mailadres, afspraakgegevens, notities, bedragen, en bij een klantaccount inloggegevens.</li>
        <li>Bijzondere persoonsgegevens worden niet gevraagd. De zaak legt in notities geen gezondheidsgegevens vast.</li>
    </ul>

    <h2>3. Verplichtingen van de Verwerker</h2>
    <ul>
        <li>Verwerkt gegevens alleen op schriftelijke instructie van de Verwerkingsverantwoordelijke; de instructie is deze overeenkomst en het gebruik van de dienst.</li>
        <li>Zorgt dat medewerkers die toegang hebben tot gegevens gebonden zijn aan geheimhouding.</li>
        <li>Neemt passende technische en organisatorische maatregelen (zie bijlage).</li>
        <li>Helpt de Verwerkingsverantwoordelijke bij verzoeken van betrokkenen, door inzage, export en verwijdering mogelijk te maken.</li>
        <li>Meldt een inbreuk op persoonsgegevens zonder onredelijke vertraging, en uiterlijk binnen 48 uur nadat de Verwerker die heeft ontdekt.</li>
    </ul>

    <h2>4. Subverwerkers</h2>
    <p>De Verwerker mag subverwerkers inschakelen voor hosting en het versturen van berichten (zie de <a href="{{ route('privacy') }}">privacyverklaring</a>). Hij legt hen dezelfde verplichtingen op en blijft verantwoordelijk. Een actuele lijst is op aanvraag beschikbaar. Voorgenomen wijzigingen meldt de Verwerker vooraf; de Verwerkingsverantwoordelijke kan bezwaar maken.</p>

    <h2>5. Doorgifte buiten de EER</h2>
    <p>De Verwerker geeft geen persoonsgegevens door buiten de Europese Economische Ruimte, tenzij met passende waarborgen die de AVG vereist.</p>

    <h2>6. Controle</h2>
    <p>De Verwerker verstrekt op verzoek de informatie die nodig is om naleving aan te tonen en werkt mee aan redelijke audits, na redelijke aankondiging en op kosten van de Verwerkingsverantwoordelijke.</p>

    <h2>7. Einde van de overeenkomst</h2>
    <p>Na afloop verwijdert de Verwerker de gegevens binnen 90 dagen, of levert ze eerst terug als de Verwerkingsverantwoordelijke dat vraagt, behalve waar een wettelijke bewaarplicht geldt.</p>

    <h2>Bijlage: beveiligingsmaatregelen</h2>
    <ul>
        <li>Versleutelde verbindingen (HTTPS) en versleutelde opslag van wachtwoorden.</li>
        <li>Strikte scheiding tussen zaken: een zaak ziet nooit gegevens van een andere zaak; een kapper ziet alleen zijn eigen omzet.</li>
        <li>Toegang voor beheerders met twee-staps-verificatie en logging van beheeracties.</li>
        <li>Regelmatige back-ups en beveiligingsupdates van de servers.</li>
    </ul>

    <h2>Contact</h2>
    <p>{{ config('barbercuts.company.name') }} · <a href="mailto:{{ config('barbercuts.company.email') }}">{{ config('barbercuts.company.email') }}</a></p>
</x-legal.page>
