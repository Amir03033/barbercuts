<?php

namespace App\Listeners;

use App\Enums\AppointmentSource;
use App\Enums\CancelledBy;
use App\Enums\ShopRole;
use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Events\AppointmentRescheduled;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\StaffMember;
use App\Models\User;
use App\Notifications\StaffAppointmentUpdate;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;

/**
 * Laat de kapper weten dat een klant online heeft geboekt, geannuleerd of verplaatst (§7.6).
 * Gaat naar het account van de kapper; heeft die geen account, dan naar de eigenaar(s).
 */
class NotifyStaffOfCustomerChange
{
    public function booked(AppointmentBooked $event): void
    {
        if ($event->appointment->source === AppointmentSource::Online) {
            $this->notify($event->appointment, StaffAppointmentUpdate::BOOKED);
        }
    }

    public function cancelled(AppointmentCancelled $event): void
    {
        if ($event->appointment->cancelled_by === CancelledBy::Customer) {
            $this->notify($event->appointment, StaffAppointmentUpdate::CANCELLED);
        }
    }

    public function rescheduled(AppointmentRescheduled $event): void
    {
        if ($event->byCustomer) {
            $this->notify($event->appointment, StaffAppointmentUpdate::RESCHEDULED);
        }
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(AppointmentBooked::class, [self::class, 'booked']);
        $events->listen(AppointmentCancelled::class, [self::class, 'cancelled']);
        $events->listen(AppointmentRescheduled::class, [self::class, 'rescheduled']);
    }

    private function notify(Appointment $appointment, string $type): void
    {
        // In een demozaak (fase 6) gaat er nooit een bericht uit.
        if (Barbershop::query()->whereKey($appointment->barbershop_id)->where('is_demo', true)->exists()) {
            return;
        }

        $this->recipients($appointment)->each(
            fn (User $user) => $user->notify(new StaffAppointmentUpdate($appointment->id, $type))
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Appointment $appointment): Collection
    {
        $userId = StaffMember::withoutGlobalScopes()->whereKey($appointment->staff_member_id)->value('user_id');

        if ($userId !== null) {
            return User::query()->whereKey($userId)->get();
        }

        return Barbershop::findOrFail($appointment->barbershop_id)
            ->users()
            ->wherePivot('role', ShopRole::Owner->value)
            ->get();
    }
}
