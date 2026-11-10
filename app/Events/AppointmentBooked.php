<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;

class AppointmentBooked
{
    use Dispatchable;

    public function __construct(public Appointment $appointment) {}
}
