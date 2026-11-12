<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use App\Services\Messaging\ProviderResult;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Een kort bericht van een zaak aan een klant met één link (fase 8): "we missen je" of "er is een plek vrij".
 * Gaat per mail of per sms, nooit allebei.
 */
class CustomerNudge extends Notification
{
    /** Uitkomst van de sms-provider, door het kanaal gezet. */
    public ?ProviderResult $result = null;

    /**
     * @param  'mail'|'sms'  $channel
     */
    public function __construct(
        public string $channel,
        public string $subject,
        public string $greeting,
        public string $body,
        public string $actionLabel,
        public string $actionUrl,
        public ?string $footer = null,
        public ?string $footerUrl = null,
    ) {}

    /**
     * @return list<class-string|string>
     */
    public function via(object $notifiable): array
    {
        return [$this->channel === 'sms' ? SmsChannel::class : 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting($this->greeting)
            ->line($this->body)
            ->action($this->actionLabel, $this->actionUrl);

        if ($this->footer !== null && $this->footerUrl !== null) {
            $mail->line("[{$this->footer}]({$this->footerUrl})");
        }

        return $mail;
    }

    public function toSms(object $notifiable): string
    {
        return trim($this->body.' '.$this->actionUrl.($this->footerUrl !== null ? ' Afmelden: '.$this->footerUrl : ''));
    }
}
