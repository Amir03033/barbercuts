<?php

namespace App\Services\Booking;

use App\Enums\AppointmentSource;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffMember;
use Carbon\CarbonImmutable;

/**
 * Alles wat nodig is om een afspraak te boeken, voor elk kanaal (§7.3).
 */
final readonly class BookingData
{
    /**
     * @param  StaffMember|null  $staff  null = geen voorkeur
     * @param  CarbonImmutable  $startsAt  in UTC
     * @param  Customer|CustomerInput  $customer  bestaande klant of {naam, telefoon, e-mail?}
     */
    public function __construct(
        public Barbershop $shop,
        public Service $service,
        public ?StaffMember $staff,
        public CarbonImmutable $startsAt,
        public Customer|CustomerInput $customer,
        public AppointmentSource $source = AppointmentSource::Online,
        public ?string $notes = null,
    ) {}
}
