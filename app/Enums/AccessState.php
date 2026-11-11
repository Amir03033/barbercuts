<?php

namespace App\Enums;

/**
 * Toegangsstatus van een zaak (docs/BOUWOPDRACHT.md §7.7).
 */
enum AccessState: string
{
    case Trial = 'trial';
    case Free = 'free';
    case Active = 'active';
    case PastDue = 'past_due';
    case Expired = 'expired';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Proefperiode',
            self::Free => 'Gratis',
            self::Active => 'Betaald',
            self::PastDue => 'Betaling mislukt',
            self::Expired => 'Verlopen',
            self::Suspended => 'Geblokkeerd',
        };
    }

    /**
     * Mag de eigenaar of kapper in /app dingen wijzigen? Bij Expired en Suspended is /app alleen-lezen.
     */
    public function isWritable(): bool
    {
        return in_array($this, [self::Trial, self::Free, self::Active, self::PastDue], true);
    }

    /**
     * Is de boekingspagina open voor klanten?
     */
    public function canTakeBookings(): bool
    {
        return $this->isWritable();
    }

    public function color(): string
    {
        return match ($this) {
            self::Trial => 'info',
            self::Free => 'gray',
            self::Active => 'success',
            self::PastDue => 'warning',
            self::Expired => 'danger',
            self::Suspended => 'danger',
        };
    }
}
