<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Bevestiging van een afspraak per e-mail (eerste versie van §7.6). Kort, met de beheerlink.
 */
class AppointmentConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $appointmentId) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = Appointment::withoutGlobalScopes()->with(['barbershop', 'staffMember', 'customer'])->findOrFail($this->appointmentId);
        $shop = $appointment->barbershop;

        return (new MailMessage)
            ->subject("Je afspraak bij {$shop->name}: ".$appointment->whenLabel())
            ->greeting("Hoi {$appointment->customer->name},")
            ->line("Je afspraak bij {$shop->name} staat vast.")
            ->line("**{$appointment->service_name}** bij {$appointment->staffMember->name}")
            ->line('**'.ucfirst($appointment->whenLabel(long: true)).'** · '.duration_label($appointment->duration).' · '.money($appointment->price_cents))
            ->action('Afspraak bekijken', $appointment->manageUrl())
            ->line('Via de knop kun je je afspraak verplaatsen, annuleren of in je agenda zetten.');
    }
}
