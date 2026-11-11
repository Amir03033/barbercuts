<?php

namespace App\Listeners;

use App\Models\Appointment;
use App\Models\MessageLog;
use App\Notifications\AppointmentMessage;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\StaffAppointmentUpdate;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use Throwable;

/**
 * Zet elk verstuurd bericht (mail, push, sms, WhatsApp) in message_logs, voor de statistiek en kosten in /admin (§5, §11).
 * Mislukte verzendingen van klantberichten logt NotificationDeliverer zelf.
 */
class LogSentMessage
{
    public function handle(NotificationSent $event): void
    {
        $channel = match ($event->channel) {
            'mail' => 'mail',
            WebPushChannel::class => 'push',
            SmsChannel::class => 'sms',
            WhatsAppChannel::class => 'whatsapp',
            default => null,
        };

        if ($channel === null) {
            return;
        }

        try {
            $notification = $event->notification;
            $appointmentId = $notification instanceof AppointmentMessage || $notification instanceof StaffAppointmentUpdate ? $notification->appointmentId : null;

            MessageLog::create([
                'barbershop_id' => $appointmentId === null ? null : Appointment::withoutGlobalScopes()->whereKey($appointmentId)->value('barbershop_id'),
                'channel' => $channel,
                'to' => $this->recipient($event, $channel),
                'type' => match (true) {
                    $notification instanceof AppointmentMessage => $notification->type->value,
                    $notification instanceof StaffAppointmentUpdate => 'staff_'.$notification->type,
                    default => Str::limit(Str::snake(class_basename($notification)), 30, ''),
                },
                'cost_cents' => $notification instanceof AppointmentMessage ? $notification->result?->costCents : null,
                'provider_id' => $notification instanceof AppointmentMessage ? $notification->result?->providerId : null,
                'status' => 'sent',
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function recipient(NotificationSent $event, string $channel): string
    {
        $notifiable = $event->notifiable;

        if ($notifiable instanceof AnonymousNotifiable) {
            return (string) ($notifiable->routes[$channel] ?? '');
        }

        return $channel === 'push' || ! isset($notifiable->email) ? 'user:'.($notifiable->id ?? '') : (string) $notifiable->email;
    }
}
