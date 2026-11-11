<?php

namespace App\Listeners;

use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Events\AppointmentRescheduled;
use App\Jobs\DeliverAppointmentNotification;
use App\Models\AppointmentNotification;
use App\Services\Notifications\NotificationScheduler;
use Illuminate\Events\Dispatcher;

/**
 * Plant de berichten aan de klant in bij boeken, verplaatsen en annuleren (§7.6). De berichten die nu al
 * verstuurd moeten worden gaan meteen de queue in; de rest pakt `notifications:send-due` op het juiste moment op.
 */
class ScheduleAppointmentNotifications
{
    public function __construct(private NotificationScheduler $scheduler) {}

    public function booked(AppointmentBooked $event): void
    {
        $this->dispatch($this->scheduler->booked($event->appointment));
    }

    public function rescheduled(AppointmentRescheduled $event): void
    {
        $this->dispatch($this->scheduler->rescheduled($event->appointment));
    }

    public function cancelled(AppointmentCancelled $event): void
    {
        $this->dispatch($this->scheduler->cancelled($event->appointment));
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(AppointmentBooked::class, [self::class, 'booked']);
        $events->listen(AppointmentRescheduled::class, [self::class, 'rescheduled']);
        $events->listen(AppointmentCancelled::class, [self::class, 'cancelled']);
    }

    /**
     * @param  list<AppointmentNotification>  $notifications
     */
    private function dispatch(array $notifications): void
    {
        foreach ($notifications as $notification) {
            DeliverAppointmentNotification::dispatch($notification->id);
        }
    }
}
