<?php

namespace App\Services\Booking;

use App\Exceptions\BookingException;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\User;
use libphonenumber\NumberParseException;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Klant vinden of aanmaken binnen een zaak (§6.5): zoeken op telefoonnummer in E.164.
 */
class CustomerResolver
{
    public function resolve(Barbershop $shop, CustomerInput $input, ?User $linkTo = null): Customer
    {
        $name = trim($input->name);
        $email = $input->email !== null && trim($input->email) !== '' ? strtolower(trim($input->email)) : null;

        if ($name === '') {
            throw new BookingException('Vul je naam in.');
        }

        $phone = self::normalizePhone($input->phone);
        $query = fn () => Customer::withoutGlobalScopes()->where('barbershop_id', $shop->id);

        // Een ingelogde klant heeft hooguit één klantrecord per zaak.
        $customer = $linkTo !== null ? $query()->where('user_id', $linkTo->id)->first() : null;
        $customer ??= $query()->where('phone', $phone)->first();

        if ($customer === null) {
            $customer = new Customer(['name' => $name, 'phone' => $phone, 'email' => $email]);
            $customer->barbershop_id = $shop->id;
        } else {
            // Bestaande klant: naam en e-mail alleen invullen als ze leeg waren.
            $customer->name = $customer->name ?: $name;
            $customer->email = $customer->email ?: $email;
        }

        if ($linkTo !== null && $customer->user_id === null) {
            $customer->user_id = $linkTo->id;
        }

        $customer->save();

        return $customer;
    }

    /**
     * Telefoonnummer naar E.164 (+31612345678). Nummers zonder landcode zijn Nederlands.
     */
    public static function normalizePhone(string $phone): string
    {
        try {
            $number = new PhoneNumber(trim($phone), 'NL');

            if (! $number->isValid()) {
                throw new BookingException('Vul een geldig telefoonnummer in, bijvoorbeeld 06 12345678.');
            }

            return $number->formatE164();
        } catch (NumberParseException) {
            throw new BookingException('Vul een geldig telefoonnummer in, bijvoorbeeld 06 12345678.');
        }
    }
}
