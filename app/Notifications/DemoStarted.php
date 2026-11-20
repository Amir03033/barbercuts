<?php

namespace App\Notifications;

use App\Models\DemoVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Seintje aan een pitcher: iemand heeft zojuist een demo gestart via zijn demolink. Per push als hij meldingen heeft
 * aangezet op een apparaat, anders per mail (nooit allebei). Bevat niets over de bezoeker behalve, bij een
 * persoonlijke demo, de naam van de zaak die de pitcher zelf heeft ingevuld.
 */
class DemoStarted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $visitId) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return method_exists($notifiable, 'pushSubscriptions') && $notifiable->pushSubscriptions()->exists()
            ? [WebPushChannel::class]
            : ['mail'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function text(): array
    {
        $visit = DemoVisit::query()->with('invite')->find($this->visitId);
        $shop = $visit?->invite?->shop_name;

        return $shop !== null
            ? ["{$shop} bekijkt je demo", "{$shop} heeft zojuist de demo geopend die je voor ze klaarzette. Een goed moment om even te bellen."]
            : ['Iemand bekijkt je demo', 'Er is zojuist een demo gestart via jouw demolink. Een goed moment om op te volgen.'];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        [$title, $body] = $this->text();

        return (new WebPushMessage)
            ->title($title)
            ->icon('/icons/icon-192.png?v=4')
            ->body($body)
            ->tag('demo-started-'.$this->visitId)
            ->data(['url' => route('pitcher.overview')]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$title, $body] = $this->text();

        return (new MailMessage)
            ->subject($title)
            ->greeting('Hoi '.explode(' ', trim((string) $notifiable->name))[0].',')
            ->line($body)
            ->line('De demo blijft '.(int) config('barbercuts.demo.hours').' uur staan. Meldt de zaak zich aan, dan zie je dat in je overzicht.')
            ->action('Naar mijn overzicht', route('pitcher.overview'));
    }
}
