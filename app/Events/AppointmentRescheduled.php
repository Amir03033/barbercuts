<?php

namespace App\Events;

use App\Models\Appointment;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Events\Dispatchable;

class AppointmentRescheduled
{
    use Dispatchable;

    public function __construct(public Appointment $appointment, public CarbonInterface $previousStartsAt) {}
}
