<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\LoyaltyCard;
use Illuminate\Support\Facades\DB;

/**
 * Spaarkaart (fase 9): een stempel bij een voltooide afspraak, verzilveren bij een volle kaart, en alles terugdraaien
 * als het voltooien ongedaan wordt gemaakt. Het saldo staat op de klant (`customers.loyalty_stamps`).
 */
class LoyaltyService
{
    /**
     * De actieve spaarkaart van een zaak, of null.
     */
    public function cardFor(int $shopId): ?LoyaltyCard
    {
        return LoyaltyCard::withoutGlobalScopes()->where('barbershop_id', $shopId)->where('is_active', true)->first();
    }

    public function countsService(LoyaltyCard $card, int $serviceId): bool
    {
        $linked = DB::table('loyalty_card_service')->where('loyalty_card_id', $card->id)->pluck('service_id')->all();

        return $linked === [] || in_array($serviceId, $linked, true);
    }

    public function canRedeem(?LoyaltyCard $card, Customer $customer): bool
    {
        return $card !== null && $customer->loyalty_stamps >= $card->stamps_required;
    }

    /**
     * Bij het voltooien (binnen de transactie van AppointmentService): verzilveren, of anders een stempel erbij.
     * Zet de velden op de afspraak; opslaan doet de aanroeper.
     */
    public function completed(Appointment $appointment, bool $redeem): void
    {
        $card = $this->cardFor($appointment->barbershop_id);
        $customer = Customer::withoutGlobalScopes()->lockForUpdate()->findOrFail($appointment->customer_id);

        if ($redeem) {
            if (! $this->canRedeem($card, $customer)) {
                throw new BookingException('Deze klant heeft nog geen volle spaarkaart.');
            }

            $this->setStamps($customer, $customer->loyalty_stamps - $card->stamps_required);
            $appointment->loyalty_redeemed_stamps = $card->stamps_required;

            return;
        }

        if ($card !== null && $this->countsService($card, $appointment->service_id)) {
            $this->setStamps($customer, $customer->loyalty_stamps + 1);
            $appointment->loyalty_stamp = true;
        }
    }

    /**
     * Voltooien ongedaan gemaakt: de stempel eraf, of de verzilverde stempels terug.
     */
    public function undone(Appointment $appointment): void
    {
        if (! $appointment->loyalty_stamp && $appointment->loyalty_redeemed_stamps === 0) {
            return;
        }

        $customer = Customer::withoutGlobalScopes()->lockForUpdate()->findOrFail($appointment->customer_id);

        $this->setStamps($customer, $customer->loyalty_stamps - ($appointment->loyalty_stamp ? 1 : 0) + $appointment->loyalty_redeemed_stamps);

        $appointment->loyalty_stamp = false;
        $appointment->loyalty_redeemed_stamps = 0;
    }

    /**
     * Met de hand een stempel erbij of eraf (bijvoorbeeld bij het overnemen van een papieren kaart).
     */
    public function adjust(Customer $customer, int $delta): int
    {
        return DB::transaction(function () use ($customer, $delta): int {
            $locked = Customer::withoutGlobalScopes()->lockForUpdate()->findOrFail($customer->id);

            return $this->setStamps($locked, $locked->loyalty_stamps + $delta);
        });
    }

    private function setStamps(Customer $customer, int $stamps): int
    {
        $stamps = max(0, min(500, $stamps));

        DB::table('customers')->where('id', $customer->id)->update(['loyalty_stamps' => $stamps]);
        $customer->loyalty_stamps = $stamps;

        return $stamps;
    }
}
