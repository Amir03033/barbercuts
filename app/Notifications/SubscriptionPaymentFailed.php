<?php

namespace App\Notifications;

use App\Models\Barbershop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aan de eigenaar: de betaling van het abonnement is mislukt. De zaak behoudt 7 dagen volledige toegang.
 */
class SubscriptionPaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $barbershopId) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shop = Barbershop::findOrFail($this->barbershopId);

        return (new MailMessage)
            ->subject("Betaling mislukt voor {$shop->name}")
            ->line("De betaling van je BarberCuts-abonnement voor {$shop->name} is niet gelukt.")
            ->line('Je kunt nog 7 dagen gewoon verder werken. Start daarna je abonnement opnieuw om de toegang te behouden.')
            ->action('Naar mijn abonnement', route('app.subscription'));
    }
}
