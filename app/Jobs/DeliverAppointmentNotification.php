<?php

namespace App\Jobs;

use App\Services\Notifications\NotificationDeliverer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Verstuurt één geplande melding. Veilig om dubbel te dispatchen: de deliverer claimt de rij atomair.
 */
class DeliverAppointmentNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $notificationId) {}

    public function handle(NotificationDeliverer $deliverer): void
    {
        $deliverer->deliver($this->notificationId);
    }
}
