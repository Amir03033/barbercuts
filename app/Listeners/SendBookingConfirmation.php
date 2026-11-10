<?php

namespace App\Listeners;

use App\Events\AppointmentBooked;
use App\Models\Customer;
use App\Notifications\AppointmentConfirmed;
use Illuminate\Support\Facades\Notification;

/**
 * Stuurt de bevestigingsmail als de klant een e-mailadres heeft. Herinneringen en andere kanalen: fase 5.
 */
class SendBookingConfirmation
{
    public function handle(AppointmentBooked $event): void
    {
        $email = Customer::withoutGlobalScopes()->whereKey($event->appointment->customer_id)->value('email');

        if ($email) {
            Notification::route('mail', $email)->notify(new AppointmentConfirmed($event->appointment->id));
        }
    }
}
