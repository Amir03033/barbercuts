<?php

namespace App\Services\Notifications;

use App\Enums\AppointmentStatus;
use App\Enums\MessageChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\Appointment;
use App\Models\AppointmentNotification;
use App\Models\MessageLog;
use App\Models\Review;
use App\Notifications\AppointmentMessage;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Verstuurt één geplande melding. Nooit dubbel: de rij wordt eerst atomair van `pending` naar `sending` gezet,
 * en alleen wie dat lukt, verstuurt. Een mislukte verzending wordt `failed` en niet automatisch herhaald.
 */
class NotificationDeliverer
{
    public function __construct(private ChannelResolver $resolver) {}

    /**
     * Geeft de eindstatus terug, of null als de rij niet (meer) aan de beurt was of al is opgepakt.
     */
    public function deliver(int $notificationId): ?NotificationStatus
    {
        $claimed = AppointmentNotification::query()
            ->whereKey($notificationId)
            ->where('status', NotificationStatus::Pending->value)
            ->where('send_at', '<=', now())
            ->update(['status' => NotificationStatus::Sending->value]);

        if ($claimed !== 1) {
            return null;
        }

        $notification = AppointmentNotification::query()->findOrFail($notificationId);
        $appointment = Appointment::withoutGlobalScopes()->with(['barbershop', 'customer.user', 'staffMember'])->find($notification->appointment_id);

        $reason = $this->skipReason($appointment, $notification->type);

        if ($reason !== null || $appointment === null) {
            return $this->finish($notification, NotificationStatus::Skipped, error: $reason);
        }

        $choice = $this->resolver->resolve($appointment);

        if ($choice->channel === null) {
            return $this->finish($notification, NotificationStatus::Skipped, error: $choice->skipReason);
        }

        $message = new AppointmentMessage($appointment->id, $notification->type, $choice->channel);
        $to = $this->recipient($appointment, $choice->channel);

        try {
            Notification::sendNow($this->notifiable($appointment, $choice->channel), $message);
        } catch (Throwable $exception) {
            report($exception);
            $this->log($appointment, $notification->type, $choice->channel, $to, 'failed', $message, Str::limit($exception->getMessage(), 240, ''));

            return $this->finish($notification, NotificationStatus::Failed, $choice->channel, Str::limit($exception->getMessage(), 240, ''));
        }

        return $this->finish($notification, NotificationStatus::Sent, $choice->channel);
    }

    private function skipReason(?Appointment $appointment, NotificationType $type): ?string
    {
        if ($appointment === null) {
            return 'Afspraak bestaat niet meer.';
        }

        if ($type === NotificationType::Cancelled) {
            return null;
        }

        if ($type === NotificationType::ReviewRequest) {
            return match (true) {
                $appointment->status !== AppointmentStatus::Completed => 'Afspraak is niet voltooid.',
                Review::withoutGlobalScopes()->where('appointment_id', $appointment->id)->exists() => 'Er is al een review.',
                default => null,
            };
        }

        if ($appointment->status !== AppointmentStatus::Scheduled) {
            return 'Afspraak is niet meer gepland.';
        }

        if ($type->isReminder() && ! $appointment->starts_at->isFuture()) {
            return 'Afspraak is al begonnen.';
        }

        return null;
    }

    private function notifiable(Appointment $appointment, MessageChannel $channel): object
    {
        if ($channel === MessageChannel::Push) {
            return $appointment->customer->user ?? throw new \LogicException('Push zonder account.');
        }

        $route = new AnonymousNotifiable;

        return match ($channel) {
            MessageChannel::Mail => $route->route('mail', $appointment->customer->email),
            MessageChannel::Sms => $route->route('sms', $appointment->customer->phone),
            default => $route->route('whatsapp', $appointment->customer->phone),
        };
    }

    private function recipient(Appointment $appointment, MessageChannel $channel): string
    {
        return match ($channel) {
            MessageChannel::Mail => (string) $appointment->customer->email,
            MessageChannel::Push => 'user:'.$appointment->customer->user_id,
            default => (string) $appointment->customer->phone,
        };
    }

    private function finish(AppointmentNotification $notification, NotificationStatus $status, ?MessageChannel $channel = null, ?string $error = null): NotificationStatus
    {
        $notification->update([
            'status' => $status,
            'channel' => $channel,
            'error' => $error,
            'sent_at' => $status === NotificationStatus::Sent ? now() : null,
        ]);

        return $status;
    }

    private function log(Appointment $appointment, NotificationType $type, MessageChannel $channel, string $to, string $status, AppointmentMessage $message, ?string $error): void
    {
        MessageLog::create([
            'barbershop_id' => $appointment->barbershop_id,
            'channel' => $channel->value,
            'to' => $to,
            'type' => $type->value,
            'cost_cents' => $message->result?->costCents,
            'provider_id' => $message->result?->providerId,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
