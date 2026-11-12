<?php

namespace App\Services;

use App\Enums\AppointmentSource;
use App\Enums\WaitlistStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingData;
use App\Services\Booking\CustomerInput;
use App\Services\Booking\CustomerResolver;
use App\Support\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Wachtlijst bij volle dagen (fase 8). Komt er op een dag een plek vrij, dan krijgt de eerste op de lijst een bericht
 * en een kwartier voorrang; reageert die niet, dan is de volgende aan de beurt. De plek wordt niet vastgehouden:
 * wie het eerst boekt, heeft hem.
 */
class WaitlistService
{
    public function __construct(
        private AvailabilityService $availability,
        private CustomerResolver $customers,
        private CustomerMessenger $messenger,
        private CurrentShop $currentShop,
    ) {}

    public function join(Barbershop $shop, Service $service, ?StaffMember $staff, string $date, CustomerInput $input, ?User $user = null): WaitlistEntry
    {
        $settings = ShopSetting::withoutGlobalScopes()->where('barbershop_id', $shop->id)->first() ?? new ShopSetting;
        $today = CarbonImmutable::now($shop->timezone)->startOfDay();
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? CarbonImmutable::parse($date, $shop->timezone) : null;

        if (! $settings->waitlist_enabled || ! $shop->accessState()->canTakeBookings()) {
            throw new BookingException('De wachtlijst is bij deze zaak niet beschikbaar.');
        }

        if ($day === null || $day < $today || $day > $today->addDays($settings->max_days_ahead)) {
            throw new BookingException('Kies een dag waarvoor je op de wachtlijst wilt.');
        }

        if ($user === null && ! $settings->allow_guest_booking) {
            throw new BookingException('Log in om je op de wachtlijst te zetten.');
        }

        $customer = $this->customers->resolve($shop, $input, $user);

        if ($this->messenger->channelFor($shop, $customer) === null) {
            throw new BookingException('Vul een e-mailadres in, dan sturen we je een bericht zodra er een plek vrijkomt.');
        }

        $existing = WaitlistEntry::withoutGlobalScopes()
            ->where('barbershop_id', $shop->id)->where('customer_id', $customer->id)->where('service_id', $service->id)
            ->whereDate('date', $day->toDateString())
            ->whereIn('status', [WaitlistStatus::Waiting->value, WaitlistStatus::Offered->value])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $entry = new WaitlistEntry;
        $entry->barbershop_id = $shop->id;
        $entry->customer_id = $customer->id;
        $entry->service_id = $service->id;
        $entry->staff_member_id = $staff?->id;
        $entry->date = Carbon::parse($day->toDateString());
        $entry->token = Str::random(40);
        $entry->status = WaitlistStatus::Waiting;
        $entry->save();

        return $entry;
    }

    /**
     * De vrije tijden voor deze inschrijving op haar dag, in UTC.
     *
     * @return list<CarbonImmutable>
     */
    public function slots(WaitlistEntry $entry): array
    {
        $shop = Barbershop::query()->findOrFail($entry->barbershop_id);

        return $this->currentShop->run($shop, function () use ($entry, $shop): array {
            $service = Service::query()->where('is_active', true)->find($entry->service_id);
            $staff = $entry->staff_member_id === null ? null : StaffMember::query()->where('is_active', true)->find($entry->staff_member_id);

            if ($service === null || ($entry->staff_member_id !== null && $staff === null)) {
                return [];
            }

            return $this->availability->slots($shop, $service, $staff, $entry->date->toDateString());
        });
    }

    /**
     * Kijk voor één dag van één zaak of er iemand aan de beurt is, en bied dan de plek aan.
     */
    public function advance(int $shopId, string $date): ?WaitlistEntry
    {
        $entries = fn () => WaitlistEntry::withoutGlobalScopes()->where('barbershop_id', $shopId)->whereDate('date', $date);

        // Voorrang verlopen zonder boeking: die klant heeft zijn kans gehad.
        $entries()->where('status', WaitlistStatus::Offered->value)->where('offer_expires_at', '<=', now())
            ->update(['status' => WaitlistStatus::Missed->value]);

        if ($entries()->where('status', WaitlistStatus::Offered->value)->exists()) {
            return null;
        }

        foreach ($entries()->where('status', WaitlistStatus::Waiting->value)->orderBy('id')->get() as $entry) {
            if ($this->slots($entry) === []) {
                continue;
            }

            // Alleen wie de rij als eerste omzet, verstuurt het bericht (de scheduler en een annulering kunnen samenvallen).
            $claimed = WaitlistEntry::withoutGlobalScopes()->whereKey($entry->id)->where('status', WaitlistStatus::Waiting->value)->update([
                'status' => WaitlistStatus::Offered->value,
                'offered_at' => now(),
                'offer_expires_at' => now()->addMinutes(WaitlistEntry::OFFER_MINUTES),
            ]);

            if ($claimed !== 1) {
                return null;
            }

            $entry->refresh();
            $this->notify($entry);

            return $entry;
        }

        return null;
    }

    /**
     * Elke minuut door de scheduler: verlopen inschrijvingen afsluiten en per dag kijken wie aan de beurt is.
     */
    public function advanceAll(): int
    {
        $today = CarbonImmutable::now('Europe/Amsterdam')->toDateString();

        WaitlistEntry::withoutGlobalScopes()
            ->whereIn('status', [WaitlistStatus::Waiting->value, WaitlistStatus::Offered->value])
            ->whereDate('date', '<', $today)
            ->update(['status' => WaitlistStatus::Expired->value]);

        // Demozaken slaat de scheduler over: daar komt een aanbod alleen na een annulering, zodat de pitcher het kan laten zien.
        $days = WaitlistEntry::withoutGlobalScopes()
            ->whereIn('status', [WaitlistStatus::Waiting->value, WaitlistStatus::Offered->value])
            ->whereNotIn('barbershop_id', Barbershop::query()->where('is_demo', true)->select('id'))
            ->select('barbershop_id', 'date')->distinct()->limit(500)->toBase()->get();

        $offered = 0;

        foreach ($days as $day) {
            $offered += $this->advance((int) $day->barbershop_id, substr((string) $day->date, 0, 10)) === null ? 0 : 1;
        }

        return $offered;
    }

    /**
     * De klant boekt de aangeboden plek.
     */
    public function book(WaitlistEntry $entry, CarbonImmutable $startsAt, ?User $actor = null): Appointment
    {
        if (! $entry->hasOpenOffer()) {
            throw new BookingException('Deze plek is niet meer voor je gereserveerd. Kijk op de boekpagina of er nog een tijd vrij is.');
        }

        $shop = Barbershop::query()->findOrFail($entry->barbershop_id);

        return $this->currentShop->run($shop, function () use ($entry, $shop, $startsAt, $actor): Appointment {
            $appointment = app(AppointmentService::class)->book(new BookingData(
                shop: $shop,
                service: Service::query()->findOrFail($entry->service_id),
                staff: $entry->staff_member_id === null ? null : StaffMember::query()->findOrFail($entry->staff_member_id),
                startsAt: $startsAt,
                customer: Customer::query()->findOrFail($entry->customer_id),
                source: AppointmentSource::Online,
            ), $actor);

            DB::table('waitlist_entries')->where('id', $entry->id)->update([
                'status' => WaitlistStatus::Booked->value, 'appointment_id' => $appointment->id, 'updated_at' => now(),
            ]);

            return $appointment;
        });
    }

    public function cancel(WaitlistEntry $entry): void
    {
        if (in_array($entry->status, [WaitlistStatus::Waiting, WaitlistStatus::Offered], true)) {
            DB::table('waitlist_entries')->where('id', $entry->id)->update(['status' => WaitlistStatus::Cancelled->value, 'updated_at' => now()]);
        }
    }

    private function notify(WaitlistEntry $entry): void
    {
        $shop = Barbershop::query()->findOrFail($entry->barbershop_id);
        $customer = Customer::withoutGlobalScopes()->findOrFail($entry->customer_id);
        $day = nl_date(CarbonImmutable::parse($entry->date->toDateString(), $shop->timezone), 'dddd D MMMM');

        $this->messenger->send($shop, $customer, [
            'subject' => "Er is een plek vrij bij {$shop->name} op {$day}",
            'greeting' => 'Hoi '.explode(' ', trim($customer->name))[0].',',
            'body' => "Er is een plek vrijgekomen bij {$shop->name} op {$day}. Je hebt ".WaitlistEntry::OFFER_MINUTES.' minuten voorrang; daarna gaat de plek naar de volgende op de wachtlijst.',
            'actionLabel' => 'Kies je tijd',
            'actionUrl' => $entry->url(),
        ]);
    }
}
