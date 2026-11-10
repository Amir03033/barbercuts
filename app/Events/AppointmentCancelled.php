<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;

class AppointmentCancelled
{
    use Dispatchable;

    public function __construct(public Appointment $appointment) {}
}
