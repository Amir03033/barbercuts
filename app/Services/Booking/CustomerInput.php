<?php

namespace App\Services\Booking;

/**
 * Gegevens van een (nieuwe) klant bij het boeken: naam en telefoon verplicht, e-mail optioneel (B5).
 */
final readonly class CustomerInput
{
    public function __construct(
        public string $name,
        public string $phone,
        public ?string $email = null,
    ) {}
}
