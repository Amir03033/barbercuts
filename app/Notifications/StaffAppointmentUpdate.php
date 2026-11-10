<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Bericht voor de kapper als een klant online boekt, annuleert of verplaatst (§7.6). Nu per mail; push volgt in fase 5.
 */
class StaffAppointmentUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public const string BOOKED = 'booked';

    public const string CANCELLED = 'cancelled';

    public const string RESCHEDULED = 'rescheduled';

    public function __construct(public int $appointmentId, public string $type) {}

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
        $when = $appointment->whenLabel();
        $customer = $appointment->customer->name;

        [$subject, $line] = match ($this->type) {
            self::CANCELLED => ["Geannuleerd: {$customer}, {$when}", "{$customer} heeft de afspraak geannuleerd."],
            self::RESCHEDULED => ["Verplaatst: {$customer}, nu {$when}", "{$customer} heeft de afspraak verplaatst."],
            default => ["Nieuwe afspraak: {$customer}, {$when}", "{$customer} heeft online een afspraak gemaakt."],
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hoi {$appointment->staffMember->name},")
            ->line($line)
            ->line("**{$appointment->service_name}** · ".ucfirst($appointment->whenLabel(long: true)))
            ->line("{$customer} · {$appointment->customer->phone}")
            ->action('Open de agenda', route('app.calendar', ['dag' => $appointment->localStart()->toDateString(), 'kapper' => $appointment->staff_member_id]));
    }
}
