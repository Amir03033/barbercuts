<?php

namespace App\Services\Booking;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\CancelledBy;
use App\Enums\DepositStatus;
use App\Enums\PaymentMethod;
use App\Enums\ShopRole;
use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Events\AppointmentRescheduled;
use App\Exceptions\BookingException;
use App\Jobs\CancelUnpaidDeposit;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Eén plek voor alles wat met afspraken gebeurt, voor elk kanaal (docs/BOUWOPDRACHT.md §7.3 en §7.4).
 *
 * $actor is de ingelogde gebruiker. Hoort die bij de zaak (eigenaar of kapper), dan gelden de rechten uit §6.2.
 * Anders handelt de klant zelf (ook een gast, $actor = null) en gelden de limieten van de zaak; de aanroeper
 * heeft dan al gecontroleerd dat de afspraak van deze klant is (beheerlink of /account).
 */
class AppointmentService
{
    private const string SLOT_TAKEN = 'Dit tijdstip is net geboekt. Kies een andere tijd.';

    public function __construct(
        private AvailabilityService $availability,
        private CustomerResolver $customers,
    ) {}

    public function book(BookingData $data, ?User $actor): Appointment
    {
        $shop = $data->shop;
        $service = $data->service;
        $staff = $data->staff;
        $online = $data->source === AppointmentSource::Online;
        $role = $actor?->roleIn($shop);

        // 1. Validatie
        if ($service->barbershop_id !== $shop->id || ! $service->is_active) {
            throw new BookingException('Deze dienst is niet (meer) beschikbaar.');
        }

        $eligible = $this->availability->eligibleStaff($shop, $service);

        if ($staff !== null && ($staff->barbershop_id !== $shop->id || ! $eligible->has($staff->id))) {
            throw new BookingException('Deze kapper is niet beschikbaar voor deze dienst.');
        }

        if ($online && ! $shop->is_public) {
            throw new BookingException('Online boeken is tijdelijk niet beschikbaar.');
        }

        // 2. Autorisatie
        if ($online) {
            if ($actor === null && ! $this->settings($shop)->allow_guest_booking) {
                throw new BookingException('Bij deze zaak boek je met een account. Log in of maak een account aan.');
            }
        } else {
            if ($role === null) {
                throw new AuthorizationException('Je hoort niet bij deze zaak.');
            }

            if ($role === ShopRole::Barber) {
                // Een kapper boekt alleen in de eigen agenda.
                $own = $eligible->first(fn (StaffMember $candidate): bool => $candidate->user_id === $actor->id);

                if ($own === null || ($staff !== null && $staff->id !== $own->id)) {
                    throw new AuthorizationException('Je kunt alleen in je eigen agenda boeken.');
                }

                $staff = $own;
            }
        }

        if ($data->customer instanceof Customer && $data->customer->barbershop_id !== $shop->id) {
            throw new AuthorizationException('Deze klant hoort niet bij deze zaak.');
        }

        // 4. Transactie met lock op de betrokken kapper(s)
        return DB::transaction(function () use ($data, $actor, $shop, $service, $staff, $online, $eligible): Appointment {
            $this->lockStaff($shop, $staff !== null ? [$staff->id] : $eligible->keys()->all());

            $free = $this->availability->freeStaffAt($shop, $service, $staff, $data->startsAt, online: $online);

            if ($free === []) {
                throw new BookingException(self::SLOT_TAKEN);
            }

            // 3. Klant vinden of aanmaken; een ingelogde klant boekt voor zichzelf.
            $customer = $data->customer instanceof Customer
                ? $data->customer
                : $this->customers->resolve($shop, $data->customer, $online ? $actor : null);

            $deposit = DepositCalculator::for($service);

            $appointment = new Appointment;
            $appointment->barbershop_id = $shop->id;
            $appointment->customer_id = $customer->id;
            $appointment->staff_member_id = $free[0]->id;
            $appointment->service_id = $service->id;
            $appointment->service_name = $service->name;
            $appointment->duration = $service->duration;
            $appointment->price_cents = $service->price_cents;
            $appointment->starts_at = $data->startsAt->utc();
            $appointment->ends_at = $data->startsAt->utc()->addMinutes($service->duration);
            $appointment->blocked_until = $data->startsAt->utc()->addMinutes($service->duration + $service->buffer_after);
            $appointment->status = AppointmentStatus::Scheduled;
            $appointment->source = $data->source;
            $appointment->notes = $data->notes !== null && trim($data->notes) !== '' ? trim($data->notes) : null;
            $appointment->manage_token = Str::random(40);
            $appointment->deposit_cents = $online ? $deposit : 0;
            $appointment->deposit_status = $online && $deposit > 0 ? DepositStatus::Pending : DepositStatus::None;
            $appointment->created_by_user_id = $actor?->id;
            $appointment->save();

            // 5. Na de commit: bevestiging en (later) herinneringen.
            DB::afterCommit(function () use ($appointment): void {
                event(new AppointmentBooked($appointment));

                if ($appointment->deposit_status === DepositStatus::Pending) {
                    CancelUnpaidDeposit::dispatch($appointment->id)->delay(now()->addMinutes(15));
                }
            });

            return $appointment;
        });
    }

    /**
     * Verplaatsen. $newStaff = null houdt de afspraak bij dezelfde kapper.
     */
    public function reschedule(Appointment $appointment, CarbonImmutable $newStartsAt, ?StaffMember $newStaff, ?User $actor): Appointment
    {
        $shop = $this->shop($appointment);
        $byStaff = $actor?->roleIn($shop) !== null;

        $this->ensureStatus($appointment, AppointmentStatus::Scheduled, 'Deze afspraak kan niet meer verplaatst worden.');

        if ($byStaff) {
            Gate::forUser($actor)->authorize('update', $appointment);
        } elseif (now()->gt($appointment->starts_at->copy()->subMinutes($this->settings($shop)->customer_reschedule_limit))) {
            throw new BookingException('Verplaatsen kan niet meer online. Bel de zaak om je afspraak te wijzigen.');
        }

        $target = $newStaff ?? StaffMember::withoutGlobalScopes()->findOrFail($appointment->staff_member_id);

        if ($target->barbershop_id !== $shop->id) {
            throw new BookingException('Deze kapper is niet beschikbaar voor deze dienst.');
        }

        if ($byStaff && $actor->roleIn($shop) === ShopRole::Barber && $target->user_id !== $actor->id) {
            throw new AuthorizationException('Je kunt alleen binnen je eigen agenda verplaatsen.');
        }

        // Reken met de duur van de afspraak zelf (snapshot), niet met de huidige duur van de dienst.
        $service = clone Service::withoutGlobalScopes()->findOrFail($appointment->service_id);
        $service->duration = $appointment->duration;

        return DB::transaction(function () use ($appointment, $newStartsAt, $target, $shop, $service, $byStaff): Appointment {
            $this->lockStaff($shop, array_unique([$appointment->staff_member_id, $target->id]));

            // De eigen afspraak telt niet als bezet.
            $free = $this->availability->freeStaffAt($shop, $service, $target, $newStartsAt, ignore: $appointment, online: ! $byStaff);

            if ($free === []) {
                throw new BookingException(self::SLOT_TAKEN);
            }

            $previous = $appointment->starts_at->copy();

            $appointment->staff_member_id = $target->id;
            $appointment->starts_at = $newStartsAt->utc();
            $appointment->ends_at = $newStartsAt->utc()->addMinutes($appointment->duration);
            $appointment->blocked_until = $newStartsAt->utc()->addMinutes($appointment->duration + $service->buffer_after);
            $appointment->save();

            DB::afterCommit(fn () => event(new AppointmentRescheduled($appointment, $previous, byCustomer: ! $byStaff)));

            return $appointment;
        });
    }

    public function cancel(Appointment $appointment, ?User $actor, ?string $reason = null): Appointment
    {
        $shop = $this->shop($appointment);
        $byStaff = $actor?->roleIn($shop) !== null;

        $this->ensureStatus($appointment, AppointmentStatus::Scheduled, 'Deze afspraak kan niet meer geannuleerd worden.');

        if ($byStaff) {
            Gate::forUser($actor)->authorize('update', $appointment);
        } elseif (now()->gt($appointment->starts_at->copy()->subMinutes($this->settings($shop)->customer_cancel_limit))) {
            throw new BookingException('Annuleren kan niet meer online. Bel de zaak om je afspraak te wijzigen.');
        }

        return $this->markCancelled($appointment, $byStaff ? CancelledBy::Staff : CancelledBy::Customer, $reason);
    }

    /**
     * Annuleren door het systeem, bv. als een aanbetaling niet op tijd is betaald (§7.8).
     */
    public function cancelBySystem(Appointment $appointment, string $reason): Appointment
    {
        $this->ensureStatus($appointment, AppointmentStatus::Scheduled, 'Deze afspraak kan niet meer geannuleerd worden.');

        return $this->markCancelled($appointment, CancelledBy::System, $reason);
    }

    public function complete(Appointment $appointment, User $actor, int $amountPaidCents, int $tipCents = 0, ?PaymentMethod $paymentMethod = null): Appointment
    {
        Gate::forUser($actor)->authorize('update', $appointment);

        $this->ensureStatus($appointment, AppointmentStatus::Scheduled, 'Deze afspraak kan niet meer voltooid worden.');
        $this->ensureStarted($appointment);

        if ($amountPaidCents < 0 || $tipCents < 0) {
            throw new BookingException('Het bedrag kan niet negatief zijn.');
        }

        return DB::transaction(function () use ($appointment, $amountPaidCents, $tipCents, $paymentMethod): Appointment {
            $appointment->status = AppointmentStatus::Completed;
            $appointment->amount_paid_cents = $amountPaidCents;
            $appointment->tip_cents = $tipCents;
            $appointment->payment_method = $paymentMethod;
            $appointment->completed_at = now();
            $appointment->save();

            $this->refreshLastVisit($appointment);

            return $appointment;
        });
    }

    public function markNoShow(Appointment $appointment, User $actor): Appointment
    {
        Gate::forUser($actor)->authorize('update', $appointment);

        $this->ensureStatus($appointment, AppointmentStatus::Scheduled, 'Deze afspraak kan niet meer op no-show gezet worden.');
        $this->ensureStarted($appointment);

        return DB::transaction(function () use ($appointment): Appointment {
            $appointment->status = AppointmentStatus::NoShow;

            if ($appointment->deposit_status === DepositStatus::Paid) {
                $appointment->deposit_status = DepositStatus::Forfeited;
            }

            $appointment->save();

            Customer::withoutGlobalScopes()->whereKey($appointment->customer_id)->increment('no_show_count');

            return $appointment;
        });
    }

    /**
     * Voltooien ongedaan maken, tot 24 uur daarna.
     */
    public function undoComplete(Appointment $appointment, User $actor): Appointment
    {
        Gate::forUser($actor)->authorize('update', $appointment);

        $this->ensureStatus($appointment, AppointmentStatus::Completed, 'Alleen een voltooide afspraak kun je terugzetten.');

        if ($appointment->completed_at === null || $appointment->completed_at->lt(now()->subDay())) {
            throw new BookingException('Terugzetten kan tot 24 uur na het voltooien.');
        }

        return DB::transaction(function () use ($appointment): Appointment {
            $appointment->status = AppointmentStatus::Scheduled;
            $appointment->amount_paid_cents = null;
            $appointment->tip_cents = null;
            $appointment->payment_method = null;
            $appointment->completed_at = null;
            $appointment->save();

            $this->refreshLastVisit($appointment);

            return $appointment;
        });
    }

    private function markCancelled(Appointment $appointment, CancelledBy $by, ?string $reason): Appointment
    {
        return DB::transaction(function () use ($appointment, $by, $reason): Appointment {
            $appointment->status = AppointmentStatus::Cancelled;
            $appointment->cancelled_at = now();
            $appointment->cancelled_by = $by;
            $appointment->cancellation_reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;
            $appointment->deposit_status = match ($appointment->deposit_status) {
                DepositStatus::Paid => DepositStatus::Refunded,
                DepositStatus::Pending => DepositStatus::None,
                default => $appointment->deposit_status,
            };
            $appointment->save();

            DB::afterCommit(fn () => event(new AppointmentCancelled($appointment)));

            return $appointment;
        });
    }

    /**
     * Elke overgang die niet in §7.4 staat is een BookingException.
     */
    private function ensureStatus(Appointment $appointment, AppointmentStatus $expected, string $message): void
    {
        if ($appointment->status !== $expected) {
            throw new BookingException($message);
        }
    }

    private function ensureStarted(Appointment $appointment): void
    {
        if ($appointment->starts_at->isFuture()) {
            throw new BookingException('Deze afspraak is nog niet begonnen.');
        }
    }

    /**
     * Laatste bezoek van de klant = de laatste voltooide afspraak.
     */
    private function refreshLastVisit(Appointment $appointment): void
    {
        $lastVisit = Appointment::withoutGlobalScopes()
            ->where('customer_id', $appointment->customer_id)
            ->where('status', AppointmentStatus::Completed->value)
            ->max('starts_at');

        Customer::withoutGlobalScopes()->whereKey($appointment->customer_id)->update(['last_visit_at' => $lastVisit]);
    }

    /**
     * Lock de rijen van de betrokken kappers, gesorteerd op id om deadlocks te voorkomen (§7.3, stap 4).
     *
     * @param  array<int, int>  $staffIds
     */
    private function lockStaff(Barbershop $shop, array $staffIds): void
    {
        StaffMember::withoutGlobalScopes()
            ->where('barbershop_id', $shop->id)
            ->whereIn('id', $staffIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    private function shop(Appointment $appointment): Barbershop
    {
        return Barbershop::findOrFail($appointment->barbershop_id);
    }

    private function settings(Barbershop $shop): ShopSetting
    {
        return ShopSetting::withoutGlobalScopes()->where('barbershop_id', $shop->id)->first() ?? new ShopSetting;
    }
}
