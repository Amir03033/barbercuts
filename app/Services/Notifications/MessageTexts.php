<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Models\Appointment;

/**
 * De korte tekst van een bericht aan de klant, voor push, sms en WhatsApp (§7.6). De mail heeft een eigen opmaak.
 */
class MessageTexts
{
    /**
     * @return array{title: string, body: string, url: string}
     */
    public static function for(Appointment $appointment, NotificationType $type): array
    {
        $shop = $appointment->barbershop->name;
        $when = $appointment->whenLabel();
        $detail = "{$appointment->service_name}, bij {$appointment->staffMember->name}";
        $url = $appointment->manageUrl();

        return match ($type) {
            NotificationType::Confirmation => [
                'title' => 'Je afspraak staat vast',
                'body' => "Je afspraak: {$when} bij {$shop} ({$detail}).",
                'url' => $url,
            ],
            NotificationType::Reminder1, NotificationType::Reminder2 => [
                'title' => 'Herinnering aan je afspraak',
                'body' => "Herinnering: {$when} bij {$shop} ({$detail}).",
                'url' => $url,
            ],
            NotificationType::Rescheduled => [
                'title' => 'Je afspraak is verplaatst',
                'body' => "Je afspraak bij {$shop} is verplaatst naar {$when} ({$detail}).",
                'url' => $url,
            ],
            NotificationType::Cancelled => [
                'title' => 'Je afspraak is geannuleerd',
                'body' => "Je afspraak bij {$shop} op {$when} is geannuleerd.",
                'url' => $url,
            ],
        };
    }

    /**
     * SMS: de tekst met de beheerlink erachter.
     */
    public static function sms(Appointment $appointment, NotificationType $type): string
    {
        $text = self::for($appointment, $type);

        return $type === NotificationType::Cancelled
            ? $text['body']
            : $text['body'].' Verplaatsen of annuleren: '.$text['url'];
    }
}
