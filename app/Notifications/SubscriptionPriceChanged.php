<?php

namespace App\Notifications;

use App\Models\Barbershop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aan de eigenaar: de prijs van het abonnement is aangepast (alleen als de admin "ook bestaande" kiest, §7.7).
 */
class SubscriptionPriceChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $barbershopId, public int $oldCents, public int $newCents) {}

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
            ->subject('De prijs van je BarberCuts-abonnement verandert')
            ->line("De prijs van het abonnement voor {$shop->name} verandert van ".money($this->oldCents).' naar '.money($this->newCents).' per maand.')
            ->line('De nieuwe prijs geldt bij de eerstvolgende betaling. Opzeggen kan altijd via je abonnement.')
            ->action('Naar mijn abonnement', route('app.subscription'));
    }
}
