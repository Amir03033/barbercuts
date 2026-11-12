<?php

namespace App\Notifications;

use App\Enums\MessageChannel;
use App\Enums\NotificationType;
use App\Models\Appointment;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Messaging\ProviderResult;
use App\Services\Notifications\MessageTexts;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Eén bericht aan de klant (bevestiging, herinnering, verplaatst, geannuleerd) via precies één kanaal (§7.6).
 * Wordt altijd direct verstuurd door NotificationDeliverer, nooit via de queue van Laravel, zodat de uitkomst bekend is.
 */
class AppointmentMessage extends Notification
{
    /** Uitkomst van de provider (sms en WhatsApp), door het kanaal gezet. */
    public ?ProviderResult $result = null;

    public function __construct(public int $appointmentId, public NotificationType $type, public MessageChannel $channel) {}

    /**
     * @return list<class-string|string>
     */
    public function via(object $notifiable): array
    {
        return [match ($this->channel) {
            MessageChannel::Mail => 'mail',
            MessageChannel::Push => WebPushChannel::class,
            MessageChannel::Sms => SmsChannel::class,
            MessageChannel::WhatsApp => WhatsAppChannel::class,
        }];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment();
        $shop = $appointment->barbershop;
        $when = $appointment->whenLabel();

        $mail = (new MailMessage)->greeting("Hoi {$appointment->customer->name},");

        return match ($this->type) {
            NotificationType::Confirmation => $mail
                ->subject("Je afspraak bij {$shop->name}: {$when}")
                ->line("Je afspraak bij {$shop->name} staat vast.")
                ->line($this->details($appointment))
                ->action('Afspraak bekijken', $appointment->manageUrl())
                ->line('Via de knop kun je je afspraak verplaatsen, annuleren of in je agenda zetten.'),
            NotificationType::Reminder1, NotificationType::Reminder2 => $mail
                ->subject("Herinnering: {$when} bij {$shop->name}")
                ->line("Een herinnering aan je afspraak bij {$shop->name}.")
                ->line($this->details($appointment))
                ->action('Verplaatsen of annuleren', $appointment->manageUrl()),
            NotificationType::Rescheduled => $mail
                ->subject("Je afspraak bij {$shop->name} is verplaatst naar {$when}")
                ->line("Je afspraak bij {$shop->name} is verplaatst.")
                ->line($this->details($appointment))
                ->action('Afspraak bekijken', $appointment->manageUrl()),
            NotificationType::Cancelled => $mail
                ->subject("Je afspraak bij {$shop->name} is geannuleerd")
                ->line("Je afspraak bij {$shop->name} op {$when} is geannuleerd.")
                ->line('Wil je een nieuwe afspraak maken? Dat kan via de boekingspagina van de zaak.')
                ->action('Opnieuw boeken', route('shop.book', ['slug' => $shop->slug])),
            NotificationType::ReviewRequest => $mail
                ->subject("Hoe was het bij {$shop->name}?")
                ->line("Bedankt voor je bezoek aan {$shop->name}.")
                ->line("Wil je laten weten hoe het was bij {$appointment->staffMember->name}? Het kost een paar tikken en helpt anderen een goede kapper te vinden.")
                ->action('Review achterlaten', $appointment->manageUrl()),
        };
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $text = MessageTexts::for($this->appointment(), $this->type);

        return (new WebPushMessage)
            ->title($text['title'])
            ->icon('/icons/icon-192.png')
            ->body($text['body'])
            ->tag('appointment-'.$this->appointmentId)
            ->data(['url' => $text['url']]);
    }

    public function toSms(object $notifiable): string
    {
        return MessageTexts::sms($this->appointment(), $this->type);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    public function toWhatsApp(object $notifiable): array
    {
        $appointment = $this->appointment();

        return [
            (string) config('barbercuts.whatsapp.templates.'.$this->type->value),
            [$appointment->customer->name, $appointment->barbershop->name, $appointment->whenLabel(), $appointment->manageUrl()],
        ];
    }

    private function appointment(): Appointment
    {
        return Appointment::withoutGlobalScopes()->with(['barbershop', 'staffMember', 'customer'])->findOrFail($this->appointmentId);
    }

    private function details(Appointment $appointment): string
    {
        return "**{$appointment->service_name}** bij {$appointment->staffMember->name}\n\n**".ucfirst($appointment->whenLabel(long: true)).'** · '.duration_label($appointment->duration).' · '.money($appointment->price_cents);
    }
}
