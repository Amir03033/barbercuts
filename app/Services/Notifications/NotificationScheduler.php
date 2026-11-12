<?php

namespace App\Services\Notifications;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\Appointment;
use App\Models\AppointmentNotification;
use App\Models\Barbershop;
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

    /**
     * Na het voltooien: een verzoek om een review, een paar uur later (fase 7). Niet als de zaak het uit heeft staan,
     * niet in een demozaak en hooguit één keer per 30 dagen per klant.
     */
    public function completed(Appointment $appointment): ?AppointmentNotification
    {
        $settings = ShopSetting::withoutGlobalScopes()->where('barbershop_id', $appointment->barbershop_id)->first() ?? new ShopSetting;

        if ($settings->review_request_hours === null || Barbershop::query()->whereKey($appointment->barbershop_id)->where('is_demo', true)->exists()) {
            return null;
        }

        $recentlyAsked = AppointmentNotification::query()
            ->where('type', NotificationType::ReviewRequest->value)
            ->where('status', '!=', NotificationStatus::Skipped->value)
            ->where('created_at', '>', now()->subDays(30))
            ->whereIn('appointment_id', Appointment::withoutGlobalScopes()->where('customer_id', $appointment->customer_id)->select('id'))
            ->exists();

        if ($recentlyAsked) {
            return null;
        }

        return $this->create($appointment, NotificationType::ReviewRequest, now()->addHours($settings->review_request_hours));
    }

    /**
     * Voltooien ongedaan gemaakt: een openstaand reviewverzoek vervalt.
     */
    public function completionUndone(Appointment $appointment): void
    {
        $this->skipPending($appointment, 'Voltooien ongedaan gemaakt.');
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
