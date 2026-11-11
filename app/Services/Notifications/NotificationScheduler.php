<?php

namespace App\Services\Notifications;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\Appointment;
use App\Models\AppointmentNotification;
use App\Models\ShopSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Plant de berichten aan de klant in (§7.6): bevestiging, 1e en 2e herinnering, verplaatst en geannuleerd.
 * Verzenden gebeurt later, door NotificationDeliverer.
 */
class NotificationScheduler
{
    /**
     * Na het boeken: een bevestiging en de herinneringen. Geeft de direct te versturen rijen terug.
     *
     * @return list<AppointmentNotification>
     */
    public function booked(Appointment $appointment): array
    {
        $immediate = [$this->create($appointment, NotificationType::Confirmation, now())];
        $this->scheduleReminders($appointment);

        return $immediate;
    }

    /**
     * Na het verplaatsen: openstaande herinneringen vervallen, nieuwe worden ingepland en de klant krijgt een bericht.
     *
     * @return list<AppointmentNotification>
     */
    public function rescheduled(Appointment $appointment): array
    {
        $this->skipPending($appointment, 'Afspraak verplaatst.');
        $immediate = [$this->create($appointment, NotificationType::Rescheduled, now())];
        $this->scheduleReminders($appointment);

        return $immediate;
    }

    /**
     * Na het annuleren: openstaande berichten vervallen en de klant krijgt een bericht.
     *
     * @return list<AppointmentNotification>
     */
    public function cancelled(Appointment $appointment): array
    {
        $this->skipPending($appointment, 'Afspraak geannuleerd.');

        return [$this->create($appointment, NotificationType::Cancelled, now())];
    }

    private function scheduleReminders(Appointment $appointment): void
    {
        $settings = ShopSetting::withoutGlobalScopes()->where('barbershop_id', $appointment->barbershop_id)->first() ?? new ShopSetting;
        $start = CarbonImmutable::instance($appointment->starts_at);

        foreach ([[NotificationType::Reminder1, $settings->reminder_1_minutes], [NotificationType::Reminder2, $settings->reminder_2_minutes]] as [$type, $minutes]) {
            if ($minutes === null) {
                continue;
            }

            $sendAt = $start->subMinutes((int) $minutes);

            if ($sendAt->isFuture()) {
                $this->create($appointment, $type, $sendAt);
            }
        }
    }

    private function skipPending(Appointment $appointment, string $reason): void
    {
        AppointmentNotification::query()
            ->where('appointment_id', $appointment->id)
            ->where('status', NotificationStatus::Pending->value)
            ->update(['status' => NotificationStatus::Skipped->value, 'error' => $reason]);
    }

    private function create(Appointment $appointment, NotificationType $type, CarbonImmutable|Carbon $sendAt): AppointmentNotification
    {
        return AppointmentNotification::create([
            'appointment_id' => $appointment->id,
            'type' => $type,
            'send_at' => $sendAt,
            'status' => NotificationStatus::Pending,
        ]);
    }
}
