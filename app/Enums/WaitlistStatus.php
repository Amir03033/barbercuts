<?php

namespace App\Enums;

enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Offered = 'offered';
    case Booked = 'booked';
    case Missed = 'missed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Wacht',
            self::Offered => 'Plek aangeboden',
            self::Booked => 'Geboekt',
            self::Missed => 'Niet gereageerd',
            self::Cancelled => 'Afgemeld',
            self::Expired => 'Verlopen',
        };
    }
}
