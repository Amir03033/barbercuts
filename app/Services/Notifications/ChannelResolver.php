<?php

namespace App\Services\Notifications;

use App\Enums\MessageChannel;
use App\Models\Appointment;
use App\Models\ShopSetting;
use App\Support\Features;

/**
 * Kiest het kanaal voor één bericht aan de klant (§7.6). Altijd precies één kanaal, zodat er nooit dubbel wordt verstuurd:
 *
 * 1. push, als de klant een account met push-abonnement heeft, de zaak push toestaat en de klant het niet heeft uitgezet;
 * 2. een kanaal dat de klant zelf heeft gekozen in /account (WhatsApp of sms), als de zaak en het platform dat aan hebben;
 * 3. e-mail, als er een adres is en de klant mail niet heeft uitgezet;
 * 4. sms, als de zaak en het platform dat aan hebben;
 * 5. anders: overslaan, met een reden.
 */
class ChannelResolver
{
    public function resolve(Appointment $appointment): ChannelChoice
    {
        $customer = $appointment->customer;
        $user = $customer->user;
        $settings = ShopSetting::withoutGlobalScopes()->where('barbershop_id', $appointment->barbershop_id)->first() ?? new ShopSetting;
        $allowed = (array) $settings->reminder_channels;
        $phone = $customer->phone;

        $whatsapp = $settings->whatsapp_enabled && Features::whatsapp() && $phone;
        $sms = $settings->sms_enabled && Features::sms() && $phone;

        if ($user !== null && in_array('push', $allowed, true) && $user->prefersChannel('push') && $user->pushSubscriptions()->exists()) {
            return new ChannelChoice(MessageChannel::Push);
        }

        if ($user !== null && $whatsapp && $user->prefersChannel('whatsapp')) {
            return new ChannelChoice(MessageChannel::WhatsApp);
        }

        if ($user !== null && $sms && $user->prefersChannel('sms')) {
            return new ChannelChoice(MessageChannel::Sms);
        }

        if ($customer->email && ($user === null || $user->prefersChannel('mail'))) {
            return new ChannelChoice(MessageChannel::Mail);
        }

        if ($sms) {
            return new ChannelChoice(MessageChannel::Sms);
        }

        return new ChannelChoice(null, $customer->email ? 'De klant heeft mail uitgezet en er is geen ander kanaal.' : 'Geen e-mailadres en sms staat uit.');
    }
}
