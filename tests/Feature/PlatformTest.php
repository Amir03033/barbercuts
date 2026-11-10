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
