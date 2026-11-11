<?php

namespace App\Providers;

use App\Http\Middleware\DisableShopScope;
use App\Http\Middleware\ResolveShopFromApp;
use App\Http\Middleware\ResolveShopFromSlug;
use App\Listeners\NotifyStaffOfCustomerChange;
use App\Services\Billing\BillingGateway;
use App\Services\Billing\MollieBillingGateway;
use App\Support\CurrentShop;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentShop::class);
        $this->app->bind(BillingGateway::class, MollieBillingGateway::class);
    }

    public function boot(): void
    {
        Event::subscribe(NotifyStaffOfCustomerChange::class);

        // De layouts (platform, app, shop) ook als Blade-component te gebruiken: <x-layouts::platform>.
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        // De zaak moet ook bekend zijn bij Livewire-updaterequests (§6.3).
        Livewire::addPersistentMiddleware([
            ResolveShopFromApp::class,
            ResolveShopFromSlug::class,
            DisableShopScope::class,
        ]);
    }
}
