<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Uitnodiging voor een pitcher (fase 6): via de link kiest de pitcher een wachtwoord en komt daarna in de demo.
 */
class PitcherInvitation extends Notification
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('demo.welcome', now()->addDays(7), ['user' => $notifiable->getKey()]);

        return (new MailMessage)
            ->subject('Je toegang tot de BarberCuts-demo')
            ->greeting("Hoi {$notifiable->name},")
            ->line('Je hebt toegang gekregen tot de demo van BarberCuts. Daarmee laat je kapperszaken zien hoe de app werkt, met een eigen gevulde demozaak.')
            ->action('Wachtwoord kiezen', $url)
            ->line('Deze link is 7 dagen geldig. Daarna log je in op '.route('login').' en ga je naar '.route('demo').'.')
            ->line('Deel deze link en de demo niet met anderen.');
    }
}
