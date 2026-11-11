<?php

namespace App\Notifications\Channels;

use App\Services\Messaging\SmsProvider;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * Notificatiekanaal 'sms': stuurt `toSms()` naar het nummer uit `routeNotificationFor('sms')` via de gekozen provider.
 * Een mislukte verzending gooit een exception; de uitkomst staat daarna op `$notification->result`.
 */
class SmsChannel
{
    public function __construct(private SmsProvider $provider) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (! is_string($to) || $to === '') {
            throw new RuntimeException('Geen telefoonnummer voor sms.');
        }

        // @phpstan-ignore-next-line method.notFound
        $notification->result = $result = $this->provider->send($to, $notification->toSms($notifiable));

        if (! $result->successful) {
            throw new RuntimeException($result->error ?? 'SMS mislukt.');
        }
    }
}
