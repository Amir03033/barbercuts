<?php

namespace App\Services\Booking;

use App\Enums\DepositType;
use App\Models\Service;
use App\Support\Features;

/**
 * Aanbetaling voor een dienst (§7.8). Staat achter de feature flag `barbercuts.features.deposits`.
 */
class DepositCalculator
{
    public static function enabled(): bool
    {
        return Features::deposits();
    }

    /**
     * Het aanbetalingsbedrag in centen; 0 als de feature uit staat.
     */
    public static function for(Service $service): int
    {
        if (! self::enabled()) {
            return 0;
        }

        return match ($service->deposit_type) {
            DepositType::None => 0,
            DepositType::Fixed => min($service->deposit_value, $service->price_cents),
            DepositType::Percent => (int) round($service->price_cents * min($service->deposit_value, 100) / 100),
        };
    }
}
