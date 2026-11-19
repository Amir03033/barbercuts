<?php

it('shows the landing page', function () {
    $this->get('/')->assertOk()->assertSee('Start gratis');
});

it('hides the ui showcase outside the local environment', function () {
    $this->get('/_ui')->assertNotFound();
});

it('reserves every first-level route as a slug', function () {
    $reserved = config('barbercuts.reserved_slugs');

    $segments = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($route) => explode('/', trim($route->uri(), '/'))[0])
        ->filter(fn (string $segment) => $segment !== '' && ! str_starts_with($segment, '{'))
        // Livewire gebruikt een pad met hash (livewire-xxxx); slugs die met "livewire" beginnen worden in fase 1 geweigerd.
        ->reject(fn (string $segment) => str_starts_with($segment, 'livewire'))
        ->unique();

    expect($segments->diff($reserved)->values()->all())->toBe([]);
});

it('trusts the forwarded IP address only when proxies are configured', function () {
    expect(config('barbercuts.trusted_proxies'))->toBe('');

    Route::get('/_test-ip', fn () => request()->ip());

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
        ->get('/_test-ip')->assertSee('10.0.0.1');
});

it('uses the visitor IP from X-Forwarded-For once the proxy is trusted', function () {
    config(['barbercuts.trusted_proxies' => '*']);

    Route::get('/_test-ip-proxy', fn () => request()->ip());

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
        ->get('/_test-ip-proxy')->assertSee('203.0.113.9');
});
