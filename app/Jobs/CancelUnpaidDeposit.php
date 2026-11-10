<?php

namespace App\Jobs;

use App\Enums\AppointmentStatus;
use App\Enums\DepositStatus;
use App\Models\Appointment;
use App\Services\Booking\AppointmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Annuleert een afspraak waarvan de aanbetaling na 15 minuten nog niet is betaald (§7.8).
 */
class CancelUnpaidDeposit implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $appointmentId) {}

    public function handle(AppointmentService $appointments): void
    {
        $appointment = Appointment::withoutGlobalScopes()->find($this->appointmentId);

        if ($appointment === null
            || $appointment->status !== AppointmentStatus::Scheduled
            || $appointment->deposit_status !== DepositStatus::Pending) {
            return;
        }

        $appointments->cancelBySystem($appointment, 'Aanbetaling niet op tijd betaald');
    }
}
