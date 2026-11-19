<?php

namespace App\Notifications;

use App\Models\PitcherRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aan de beheerder: een pitcher heeft een verzoek of een wens doorgegeven.
 */
class PitcherRequestReceived extends Notification implements ShouldQueue
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
        $request = PitcherRequest::query()->with(['pitcher', 'barbershop'])->findOrFail($this->requestId);

        $mail = (new MailMessage)
            ->subject($request->type->label().' van '.$request->pitcher->name)
            ->line("{$request->pitcher->name} heeft een verzoek gedaan: {$request->type->label()}.");

        if ($request->barbershop !== null) {
            $mail->line("Zaak: {$request->barbershop->name} ({$request->barbershop->city})");
        }

        return $mail->line('"'.$request->message.'"')->action('Bekijk in het adminpanel', url('/admin/pitcher-requests'));
    }
}
