<?php

namespace App\Notifications;

use App\Enums\PitcherRequestStatus;
use App\Models\PitcherRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aan de pitcher: zijn verzoek is afgehandeld of afgewezen, met een korte uitleg.
 */
class PitcherRequestHandled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $requestId) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = PitcherRequest::query()->with('barbershop')->findOrFail($this->requestId);
        $done = $request->status === PitcherRequestStatus::Done;
        $shop = $request->barbershop?->name;

        $mail = (new MailMessage)
            ->subject($done ? 'Je verzoek is afgehandeld' : 'Over je verzoek')
            ->greeting("Hoi {$notifiable->name},")
            ->line($done
                ? 'Je verzoek '.($shop ? "voor {$shop} " : '').'is afgehandeld.'
                : 'Je verzoek '.($shop ? "voor {$shop} " : '').'kunnen we nu niet uitvoeren.');

        if (filled($request->answer)) {
            $mail->line($request->answer);
        }

        return $mail->action('Naar mijn aanmeldingen', route('pitcher.overview'));
    }
}
