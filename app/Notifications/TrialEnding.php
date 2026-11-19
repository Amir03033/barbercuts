<?php

namespace App\Notifications;

use App\Models\Barbershop;
use App\Models\Plan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aan de eigenaar: de proefperiode is bijna voorbij (3 dagen en 1 dag ervoor) of net verlopen.
 * Zonder abonnement kunnen klanten niet meer online boeken, dus dit bericht komt niet als verrassing.
 */
class TrialEnding extends Notification implements ShouldQueue
{
    use Queueable;

    public const THREE_DAYS = 'three_days';

    public const ONE_DAY = 'one_day';

    public const EXPIRED = 'expired';

    public function __construct(public int $barbershopId, public string $stage) {}

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
        $plan = $shop->plan ?? Plan::current();
        $price = $plan !== null ? money($plan->price_cents).' per maand' : 'een vast bedrag per maand';

        $mail = new MailMessage;

        match ($this->stage) {
            self::EXPIRED => $mail
                ->subject("Je proefperiode is voorbij: {$shop->name}")
                ->line("De proefperiode van {$shop->name} is afgelopen. Je kunt alles nog bekijken, maar niets meer wijzigen, en klanten kunnen niet meer online boeken.")
                ->line("Start je abonnement ({$price}) en je zaak staat binnen een minuut weer open. Al je gegevens blijven bewaard."),
            self::ONE_DAY => $mail
                ->subject("Morgen loopt je proefperiode af: {$shop->name}")
                ->line("Morgen is de proefperiode van {$shop->name} voorbij. Daarna kunnen klanten niet meer online boeken.")
                ->line("Start nu je abonnement ({$price}). De eerste maand gaat in na je proefperiode, dus je betaalt niet dubbel."),
            default => $mail
                ->subject("Je proefperiode loopt bijna af: {$shop->name}")
                ->line("Over 3 dagen is de gratis proefperiode van {$shop->name} voorbij.")
                ->line("Wil je door met BarberCuts? Start dan je abonnement ({$price}). De eerste maand gaat in na je proefperiode, dus je betaalt niet dubbel. Opzeggen kan altijd."),
        };

        return $mail->action('Naar mijn abonnement', route('app.subscription'));
    }
}
