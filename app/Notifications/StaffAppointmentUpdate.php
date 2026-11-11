<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Bericht voor de kapper als een klant online boekt, annuleert of verplaatst (§7.6). Per push als de kapper meldingen heeft aangezet op een apparaat, anders per mail (nooit allebei).
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
        return method_exists($notifiable, 'pushSubscriptions') && $notifiable->pushSubscriptions()->exists()
            ? [WebPushChannel::class]
            : ['mail'];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $appointment = Appointment::withoutGlobalScopes()->with(['barbershop', 'customer'])->findOrFail($this->appointmentId);

        [$title, $body] = match ($this->type) {
            self::CANCELLED => ['Afspraak geannuleerd', "{$appointment->customer->name} heeft de afspraak van ".$appointment->whenLabel().' geannuleerd.'],
            self::RESCHEDULED => ['Afspraak verplaatst', "{$appointment->customer->name} heeft de afspraak verplaatst naar ".$appointment->whenLabel().'.'],
            default => ['Nieuwe afspraak', "{$appointment->customer->name}: {$appointment->service_name}, ".$appointment->whenLabel().'.'],
        };

        return (new WebPushMessage)
            ->title($title)
            ->icon('/icons/icon-192.png')
            ->body($body)
            ->tag('staff-appointment-'.$this->appointmentId)
            ->data(['url' => route('app.calendar', ['dag' => $appointment->localStart()->toDateString(), 'kapper' => $appointment->staff_member_id])]);
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
