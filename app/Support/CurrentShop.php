<?php

namespace App\Support;

use App\Models\Barbershop;
use Closure;

/**
 * De zaak (tenant) van het huidige request (docs/BOUWOPDRACHT.md §6.3).
 *
 * - Is er een zaak gezet, dan filteren alle modellen met BelongsToShop op die zaak.
 * - Is er geen zaak gezet, dan geven die modellen in een webrequest NIETS terug (fail closed).
 * - Alleen het adminpanel, /account, /afspraak/{token} en de console werken bewust zonder scope.
 */
class CurrentShop
{
    private ?Barbershop $shop = null;

    private bool $scopeDisabled = false;

    public function set(Barbershop $shop): void
    {
        $this->shop = $shop;
    }

    public function clear(): void
    {
        $this->shop = null;
    }

    public function get(): ?Barbershop
    {
        return $this->shop;
    }

    public function id(): ?int
    {
        return $this->shop?->id;
    }

    /**
     * Voer code uit binnen een zaak en zet daarna de vorige zaak terug.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Barbershop $shop, Closure $callback): mixed
    {
        $previous = $this->shop;
        $this->shop = $shop;

        try {
            return $callback();
        } finally {
            $this->shop = $previous;
        }
    }

    /**
     * Zet de tenant-scope uit, bv. voor het Filament-adminpanel.
     */
    public function disableScope(): void
    {
        $this->scopeDisabled = true;
    }

    public function enableScope(): void
    {
        $this->scopeDisabled = false;
    }

    public function scopeIsDisabled(): bool
    {
        if ($this->scopeDisabled) {
            return true;
        }

        // Console (queue, scheduler, tinker, seeders) werkt over alle zaken heen; tests gedragen zich als het web.
        return $this->shop === null && app()->runningInConsole() && ! app()->runningUnitTests();
    }
}
