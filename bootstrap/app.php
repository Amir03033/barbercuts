<?php

use App\Http\Middleware\CaptureReferral;
use App\Http\Middleware\TrustConfiguredProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Mollie roept de webhook aan zonder CSRF-token; de echte status wordt zelf bij Mollie opgehaald.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        // De vertrouwde proxy's komen uit de config en worden pas per verzoek gelezen (zie TrustConfiguredProxies).
        $middleware->replace(TrustProxies::class, TrustConfiguredProxies::class);

        // Een link van een pitcher (?ref=code) wordt onthouden voor het aanmelden van een zaak.
        $middleware->web(append: [CaptureReferral::class]);
        $middleware->redirectGuestsTo(fn (): string => route('login'));
        $middleware->redirectUsersTo(fn (Request $request): string => $request->user()?->homeRoute() ?? route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
