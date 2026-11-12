<?php

namespace App\Services;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\ShopSetting;
use App\Notifications\CustomerNudge;
use App\Support\Features;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Stuurt een klant een los bericht van de zaak (fase 8), per mail of anders per sms. Een demozaak verstuurt nooit iets.
 */
class CustomerMessenger
{
    /**
     * Het kanaal waarlangs deze klant te bereiken is, of null.
     *
     * @return 'mail'|'sms'|null
     */
    public function channelFor(Barbershop $shop, Customer $customer): ?string
    {
        if ($customer->email) {
            return 'mail';
        }

        return $this->smsEnabled($shop) && $customer->phone ? 'sms' : null;
    }

    public function smsEnabled(Barbershop $shop): bool
    {
        $settings = ShopSetting::withoutGlobalScopes()->where('barbershop_id', $shop->id)->first();

        return Features::sms() && (bool) $settings?->sms_enabled;
    }

    /**
     * Verstuur het bericht. Geeft het gebruikte kanaal terug, of null als er niets verstuurd is.
     *
     * @param  array{subject: string, greeting: string, body: string, actionLabel: string, actionUrl: string, footer?: ?string, footerUrl?: ?string}  $message
     * @return 'mail'|'sms'|null
     */
    public function send(Barbershop $shop, Customer $customer, array $message): ?string
    {
        $channel = $this->channelFor($shop, $customer);

        if ($channel === null || $shop->is_demo) {
            return null;
        }

        $route = (new AnonymousNotifiable)->route($channel, $channel === 'mail' ? $customer->email : $customer->phone);

        try {
            Notification::sendNow($route, new CustomerNudge(
                $channel, $message['subject'], $message['greeting'], $message['body'],
                $message['actionLabel'], $message['actionUrl'], $message['footer'] ?? null, $message['footerUrl'] ?? null,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return $channel;
    }
}
