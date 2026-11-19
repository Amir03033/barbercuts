<?php

use Illuminate\Support\Facades\Route;

it('shows the Dutch 404 page for an unknown page', function () {
    $this->get('/deze-zaak-bestaat-niet')
        ->assertNotFound()
        ->assertSee('Deze pagina bestaat niet')
        ->assertSee('Kappers zoeken')
        ->assertDontSee('NotFoundHttpException');
});

it('shows the 404 page without needing the database', function () {
    $this->get('/geen/echte/pagina/hier')->assertNotFound()->assertSee('Naar de voorpagina');
});

it('renders a friendly page for every error status', function (int $status, string $text) {
    Route::get('/_test-fout/'.$status, fn () => abort($status));

    $this->get('/_test-fout/'.$status)->assertStatus($status)->assertSee($text)->assertSee('barbercuts');
})->with([
    'forbidden' => [403, 'Geen toegang'],
    'expired' => [419, 'Deze pagina is verlopen'],
    'too many requests' => [429, 'Even rustig aan'],
    'server error' => [500, 'Er ging iets mis'],
    'unavailable' => [503, 'We zijn zo terug'],
]);

it('does not leak error details on the 500 page', function () {
    Route::get('/_test-kapot', fn () => throw new RuntimeException('geheime-details'));

    config(['app.debug' => false]);

    $this->get('/_test-kapot')->assertStatus(500)->assertSee('Er ging iets mis')->assertDontSee('geheime-details');
});

it('ships a static fallback page for when PHP is down', function () {
    $html = file_get_contents(public_path('storing.html'));

    expect($html)->toContain('We zijn zo terug')->not->toContain('{{')->not->toContain('@');
});
