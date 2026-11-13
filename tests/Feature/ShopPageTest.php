<?php

use App\Livewire\App\Appearance;
use App\Models\Barbershop;
use App\Models\OpeningHour;
use App\Models\Service;
use App\Models\StaffMember;

beforeEach(function () {
    [$this->shop] = shopWithOwner(['name' => 'JF Cuts', 'slug' => 'jf-cuts']);
});

it('shows the public page with active services, barbers and opening hours', function () {
    Service::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Knippen', 'price_cents' => 2500]);
    Service::factory()->inactive()->create(['barbershop_id' => $this->shop->id, 'name' => 'Oude dienst']);
    StaffMember::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Sam']);
    StaffMember::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Mo']);
    StaffMember::factory()->inactive()->create(['barbershop_id' => $this->shop->id, 'name' => 'Vertrokken']);
    inShop($this->shop, fn () => OpeningHour::create(['day_of_week' => 2, 'opens_at' => '09:00', 'closes_at' => '18:00']));

    $this->get('/jf-cuts')->assertOk()
        ->assertSee('JF Cuts')
        ->assertSee('Knippen')->assertSee('€ 25,00')
        ->assertDontSee('Oude dienst')
        ->assertSee('Sam')->assertSee('Mo')->assertDontSee('Vertrokken')
        ->assertSee('09:00 – 18:00');
});

it('shows nothing of another shop on the public page', function () {
    [$other] = shopWithOwner(['slug' => 'andere-zaak']);
    Service::factory()->create(['barbershop_id' => $other->id, 'name' => 'Dienst van de buren']);

    $this->get('/jf-cuts')->assertOk()->assertDontSee('Dienst van de buren');
});

it('tells whether the shop is open now', function () {
    inShop($this->shop, fn () => OpeningHour::create(['day_of_week' => 2, 'opens_at' => '09:00', 'closes_at' => '18:00']));

    // Dinsdag 10 november 2026, 14:00 in Amsterdam (13:00 UTC)
    $this->travelTo('2026-11-10 13:00:00');
    $this->get('/jf-cuts')->assertSee('Nu open tot 18:00');

    $this->travelTo('2026-11-10 07:00:00');
    $this->get('/jf-cuts')->assertSee('Vandaag open vanaf 09:00');

    $this->travelTo('2026-11-10 18:30:00');
    $this->get('/jf-cuts')->assertSee('Nu gesloten');
});

it('gives a 404 for unknown and non-public shops', function () {
    Barbershop::factory()->onboarding()->create(['slug' => 'nog-bezig']);

    $this->get('/bestaat-niet')->assertNotFound();
    $this->get('/nog-bezig')->assertNotFound();
});

it('keeps platform routes ahead of the slug route', function () {
    Barbershop::factory()->create(['slug' => 'inloggen-kapper']);

    $this->get('/inloggen')->assertOk()->assertSee('Welkom terug');
    $this->get('/aanmelden')->assertOk()->assertSee('Meld je zaak aan');
});

test('de zaak zet een banner en links op haar pagina', function () {
    [$shop, $owner] = shopWithOwner();
    actingInShop($owner, $shop);

    Livewire\Livewire::test(Appearance::class)
        ->set('bannerActive', true)->set('bannerText', 'Deze maand 10% korting')
        ->set('instagram', 'https://instagram.com/studiofade/')->set('website', 'studiofade.nl')
        ->call('save')->assertHasNoErrors();

    expect($shop->fresh()->instagram)->toBe('studiofade')->and($shop->fresh()->website)->toBe('https://studiofade.nl');

    $this->get('/'.$shop->slug)->assertOk()->assertSee('Deze maand 10% korting')->assertSee('instagram.com/studiofade')->assertSee('KVK 93721382');

    $shop->update(['banner_active' => false]);
    $this->get('/'.$shop->slug)->assertDontSee('Deze maand 10% korting');
});
