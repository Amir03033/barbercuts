<div>
    <x-ui.page-header title="Vandaag" :subtitle="ucfirst(now('Europe/Amsterdam')->locale('nl')->isoFormat('dddd D MMMM'))" />

    <x-ui.empty-state icon="scissors" title="Je account staat klaar"
        text="Hier komt straks je dag: afspraken, agenda en omzet. Het aanmelden van je zaak volgt in de volgende stap." />
</div>
