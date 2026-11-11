<?php

namespace App\Notifications\Channels;

use App\Services\Messaging\WhatsAppProvider;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * Notificatiekanaal 'whatsapp': `toWhatsApp()` geeft [template, params]; verzonden via de gekozen provider.
 */
class WhatsAppChannel
{
    public function __construct(private WhatsAppProvider $provider) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (! is_string($to) || $to === '') {
            throw new RuntimeException('Geen telefoonnummer voor WhatsApp.');
        }

        // @phpstan-ignore-next-line method.notFound
        [$template, $params] = $notification->toWhatsApp($notifiable);

        // @phpstan-ignore-next-line property.notFound
        $notification->result = $result = $this->provider->sendTemplate($to, $template, $params);

        if (! $result->successful) {
            throw new RuntimeException($result->error ?? 'WhatsApp mislukt.');
        }
    }
}
