<?php

use App\Support\Features;

/*
| Landingspagina, prijzen en juridische pagina's (docs/BOUWOPDRACHT.md §9.4).
*/

it('shows the landing page with the three benefits and the call to action', function () {
    $this->get('/')->assertOk()
        ->assertSee('Afspraken, agenda en omzet voor je kapperszaak')
        ->assertSee('Klanten boeken zelf')->assertSee('Een agenda per kapper')->assertSee('Omzet in één oogopslag')
        ->assertSee('Start gratis')
        ->assertSee('Zo ziet het eruit')
        ->assertSee('href="'.route('onboarding').'"', false);
});

it('serves every static page with a title and the footer links', function (string $path, string $title) {
    $this->get($path)->assertOk()->assertSee($title)
        ->assertSee('Verwerkersovereenkomst')->assertSee('Privacy')->assertSee('Voorwaarden');
})->with([
    ['/prijzen', 'Eén prijs, alles erbij'],
    ['/privacy', 'Privacyverklaring'],
    ['/voorwaarden', 'Algemene voorwaarden'],
    ['/verwerkersovereenkomst', 'Verwerkersovereenkomst'],
    ['/contact', 'Contact'],
]);

it('shows the company details from the configuration, and leaves out what is empty', function () {
    config(['barbercuts.company.kvk' => '12345678', 'barbercuts.company.phone' => null, 'barbercuts.company.address' => 'Kapperstraat 1, Utrecht']);

    $this->get('/contact')->assertSee('KvK 12345678')->assertSee('Kapperstraat 1, Utrecht')->assertSee('hallo@barbercuts.nl')->assertDontSee('Btw-nummer')->assertDontSee('tel:');
});

it('explains the processor roles and the rights of the visitor', function () {
    $this->get('/privacy')->assertSee('verwerker')->assertSee('verwerkingsverantwoordelijke')->assertSee('Autoriteit Persoonsgegevens')->assertSee('geen tracking');
    $this->get('/verwerkersovereenkomst')->assertSee('artikel 28')->assertSee('48 uur');
    $this->get('/voorwaarden')->assertSee('7 dagen')->assertSee('opzeggen');
});

it('keeps private areas out of search engines', function () {
    $robots = (string) file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Disallow: /app')->toContain('Disallow: /admin')->toContain('Disallow: /afspraak/')->toContain('Disallow: /account');
});

it('reserves the new platform paths as slugs', function (string $slug) {
    expect(Features::reservedSlugs())->toContain($slug);
})->with(['prijzen', 'privacy', 'voorwaarden', 'verwerkersovereenkomst', 'contact', 'admin', 'webhooks', 'impersonate']);
