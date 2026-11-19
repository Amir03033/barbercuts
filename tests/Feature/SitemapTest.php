<?php

use App\Models\Barbershop;

it('lists static pages and public shops in the sitemap', function () {
    $listed = Barbershop::factory()->create(['slug' => 'knip-in-de-buurt', 'is_public' => true, 'onboarding_step' => null]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee(route('pricing'), false)
        ->assertSee($listed->bookingUrl(), false);
});

it('keeps demo, suspended, unfinished and private shops out of the sitemap', function () {
    $demo = Barbershop::factory()->create(['slug' => 'demo-zaak', 'is_public' => true, 'onboarding_step' => null, 'is_demo' => true]);
    $suspended = Barbershop::factory()->create(['slug' => 'geblokkeerde-zaak', 'is_public' => true, 'onboarding_step' => null, 'suspended_at' => now()]);
    $private = Barbershop::factory()->create(['slug' => 'prive-zaak', 'is_public' => false]);

    $response = $this->get('/sitemap.xml')->assertOk();

    foreach ([$demo, $suspended, $private] as $shop) {
        $response->assertDontSee($shop->bookingUrl(), false);
    }
});
