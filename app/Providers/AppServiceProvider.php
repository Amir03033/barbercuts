<?php

namespace App\Providers;

use App\Http\Middleware\DisableShopScope;
use App\Http\Middleware\ResolveShopFromApp;
use App\Http\Middleware\ResolveShopFromSlug;
use App\Listeners\LogSentMessage;
use App\Listeners\NotifyStaffOfCustomerChange;
use App\Listeners\ScheduleAppointmentNotifications;
use App\Services\Billing\BillingGateway;
use App\Services\Billing\MollieBillingGateway;
use App\Services\Messaging\LogSmsProvider;
use App\Services\Messaging\LogWhatsAppProvider;
use App\Services\Messaging\SmsProvider;
use App\Services\Messaging\TwilioSmsProvider;
use App\Services\Messaging\TwilioWhatsAppProvider;
use App\Services\Messaging\WhatsAppProvider;
use App\Support\CurrentShop;
use Illuminate\Notifications\Events\NotificationSent;
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
        $this->app->bind(SmsProvider::class, fn (): SmsProvider => match (config('barbercuts.sms.provider')) {
            'twilio' => new TwilioSmsProvider,
            default => new LogSmsProvider,
        });
        $this->app->bind(WhatsAppProvider::class, fn (): WhatsAppProvider => match (config('barbercuts.whatsapp.provider')) {
            'twilio' => new TwilioWhatsAppProvider,
            default => new LogWhatsAppProvider,
        });
    }

    public function boot(): void
    {
        Event::subscribe(NotifyStaffOfCustomerChange::class);
        Event::subscribe(ScheduleAppointmentNotifications::class);
        Event::listen(NotificationSent::class, LogSentMessage::class);

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
