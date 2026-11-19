<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\User;

/**
 * Kant-en-klare berichten voor pitchers (WhatsApp, Instagram, sms), met de eigen aanmeldlink en naam al ingevuld.
 * Alles tussen [haakjes] vult de pitcher zelf in, bijvoorbeeld de naam van de kapper.
 */
class PitcherMessages
{
    /**
     * @return list<array{key: string, title: string, when: string, text: string}>
     */
    public static function for(User $pitcher): array
    {
        $link = route('onboarding', ['ref' => $pitcher->referralCode()]);
        $demoLink = $pitcher->demoLink();
        $name = explode(' ', trim($pitcher->name))[0];
        $plan = Plan::current();
        $price = money($plan !== null ? $plan->price_cents : 2000);
        $days = $plan !== null ? $plan->trial_days : 14;

        return [
            [
                'key' => 'first',
                'title' => 'Eerste bericht',
                'when' => 'Je stuurt een kapper voor het eerst een bericht (WhatsApp of Instagram).',
                'text' => "Hoi [naam], ik ben {$name} van BarberCuts. Met onze app boeken je klanten zelf een afspraak bij jou, en zie jij je agenda en omzet op je telefoon. Je kunt het {$days} dagen gratis proberen, zonder creditcard. Mag ik je in 5 minuten laten zien hoe het werkt? Wanneer komt het je uit?",
            ],
            [
                'key' => 'demo_link',
                'title' => 'Demolink sturen (online)',
                'when' => 'Je vindt een kapper online (Instagram, Google, TikTok) en wilt dat hij het meteen zelf ziet.',
                'text' => "Hoi [naam], ik zag je werk online, echt mooi! Ik ben {$name} van BarberCuts: een app waarin je klanten zelf boeken en jij je agenda en omzet op je telefoon ziet. Probeer het zelf in een minuut, zonder account. Je krijgt een eigen voorbeeldzaak om op rond te klikken: {$demoLink}\nVragen? Stuur me gerust een bericht.",
            ],
            [
                'key' => 'demo_link_short',
                'title' => 'Demolink (kort, voor een DM)',
                'when' => 'Een kort berichtje in Instagram of TikTok, als eerste contact of als reactie op een vraag.',
                'text' => "Hoi [naam]! 💈 Zo ziet je agenda eruit met BarberCuts, probeer het zelf (geen account nodig): {$demoLink}",
            ],
            [
                'key' => 'after_demo',
                'title' => 'Na de demo',
                'when' => 'Een dag nadat je de demolink stuurde, als je nog niets hoorde.',
                'text' => "Hoi [naam], heb je de demo kunnen bekijken? Als het je bevalt, staat je eigen zaak in een paar minuten online, {$days} dagen gratis en zonder creditcard: {$link}\nZal ik je er even doorheen helpen?",
            ],
            [
                'key' => 'after_visit',
                'title' => 'Na het bezoek',
                'when' => 'Dezelfde dag, nadat je bij de kapper bent geweest en hij nog niet is aangemeld.',
                'text' => "Hoi [naam], leuk dat ik je net sprak! Zoals beloofd: hier meld je je zaak aan. Je kunt {$days} dagen gratis proberen, zonder creditcard: {$link}\nLukt het niet? Laat het me weten, dan help ik je.",
            ],
            [
                'key' => 'offer_help',
                'title' => 'Hulp bij aanmelden aanbieden',
                'when' => 'Na een dag of twee, als hij nog niets heeft gedaan.',
                'text' => 'Hoi [naam], heb je al kunnen kijken naar BarberCuts? Aanmelden kost ongeveer 3 minuten. Zal ik [dag] even langskomen om het samen met je te doen? Dan staat je boekingspagina meteen online.',
            ],
            [
                'key' => 'thinking',
                'title' => '“Ik denk er nog over na”',
                'when' => 'De kapper zegt dat hij er nog over wil nadenken.',
                'text' => "Helemaal goed, neem de tijd. Het mooie is dat je niets hoeft te betalen om het te proberen: {$days} dagen gratis, zonder creditcard. Meld je zaak aan in 3 minuten en kijk of het bij je past: {$link}\nHeb je vragen, dan hoor ik het graag.",
            ],
            [
                'key' => 'instagram',
                'title' => 'Instagram-bericht (kort)',
                'when' => 'Een eerste bericht via Instagram, waar je het kort moet houden.',
                'text' => "Hoi [naam]! Mooi werk 💈 Ik help kappers met een app waarin klanten zelf boeken en jij je omzet ziet. {$days} dagen gratis proberen. Zal ik je laten zien hoe het werkt?",
            ],
            [
                'key' => 'welcome',
                'title' => 'Welkom na het aanmelden',
                'when' => 'De kapper heeft zijn zaak aangemeld.',
                'text' => 'Top, [naam]! Je zaak staat online 🎉 Tip: zet je boekingslink in je Instagram-bio en download de QR-code in de app om in de zaak op te hangen. Dan boeken je klanten vanaf nu zelf. Heb je een vraag, dan ben ik er.',
            ],
            [
                'key' => 'midway',
                'title' => 'Halverwege de proef (rond dag 10)',
                'when' => 'Ongeveer 10 dagen na het aanmelden.',
                'text' => 'Hoi [naam], je proeft BarberCuts nu bijna 10 dagen. Hoe bevalt het? Over een paar dagen loopt de gratis proef af. Zal ik even helpen om er alles uit te halen, zodat je goed kunt beslissen?',
            ],
            [
                'key' => 'ending',
                'title' => 'Proef bijna voorbij (dag 12 of 13)',
                'when' => 'Een dag of twee voordat de proef afloopt. Je ziet dit in “Mijn aanmeldingen”.',
                'text' => "Hoi [naam], je gratis proef loopt bijna af. Wil je doorgaan? Sluit dan in de app een abonnement af (Meer → Abonnement, {$price} per maand, opzeggen kan altijd). Dan kunnen je klanten gewoon blijven boeken. Zal ik even helpen?",
            ],
            [
                'key' => 'expired',
                'title' => 'Proef is voorbij',
                'when' => 'De proef is afgelopen en de kapper heeft nog geen abonnement.',
                'text' => "Hoi [naam], je proefperiode is voorbij, dus je klanten kunnen nu niet meer online boeken. Een abonnement afsluiten duurt een minuutje: Meer → Abonnement in de app ({$price} per maand). Al je gegevens staan er nog. Zal ik helpen?",
            ],
        ];
    }
}
