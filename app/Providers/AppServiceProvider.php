<?php

namespace App\Providers;

use App\Http\Middleware\ResolveShopFromApp;
use App\Http\Middleware\ResolveShopFromSlug;
use App\Support\CurrentShop;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentShop::class);
    }

    public function boot(): void
    {
        // De zaak moet ook bekend zijn bij Livewire-updaterequests (§6.3).
        Livewire::addPersistentMiddleware([
            ResolveShopFromApp::class,
            ResolveShopFromSlug::class,
        ]);
    }
}
