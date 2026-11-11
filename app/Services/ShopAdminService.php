<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Barbershop;
use App\Support\Features;
use App\Support\PlatformSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Handelingen van de platformeigenaar op een zaak (docs/BOUWOPDRACHT.md §11). Elke handeling komt in het activiteitenlogboek.
 */
class ShopAdminService
{
    /**
     * Geef een zaak een gratis periode tot een datum (B12). Gaat voor alles behalve blokkeren.
     */
    public function giveFreePeriod(Barbershop $shop, CarbonInterface $until, string $reason): void
    {
        $shop->forceFill(['free_until' => $until, 'free_reason' => $reason !== '' ? $reason : null])->save();

        ActivityLog::record('shop.free_period', $shop, $shop, ['until' => $until->toDateString(), 'reason' => $reason]);
    }

    /**
     * Verleng de proefperiode met een aantal dagen, gerekend vanaf het einde of anders vanaf nu.
     */
    public function extendTrial(Barbershop $shop, int $days): void
    {
        $from = $shop->trial_ends_at?->isFuture() ? $shop->trial_ends_at : now();

        $shop->forceFill(['trial_ends_at' => $from->copy()->addDays($days)])->save();

        ActivityLog::record('shop.trial_extended', $shop, $shop, ['days' => $days, 'until' => $shop->trial_ends_at->toDateString()]);
    }

    public function suspend(Barbershop $shop, string $reason): void
    {
        $shop->forceFill(['suspended_at' => now(), 'suspended_reason' => $reason !== '' ? $reason : null])->save();

        ActivityLog::record('shop.suspended', $shop, $shop, ['reason' => $reason]);
    }

    public function unsuspend(Barbershop $shop): void
    {
        $shop->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();

        ActivityLog::record('shop.unsuspended', $shop, $shop);
    }

    /**
     * Facturering aanzetten: zaken die nog geen proefperiode, gratis periode of abonnement hebben, krijgen een proefperiode.
     * Anders zouden alle bestaande zaken op dat moment verlopen zijn. Geeft het aantal bijgewerkte zaken terug.
     */
    public function enableBilling(int $trialDays): int
    {
        $updated = Barbershop::query()
            ->whereNull('suspended_at')
            ->whereNull('trial_ends_at')
            ->where(fn ($query) => $query->whereNull('free_until')->orWhere('free_until', '<=', now()))
            ->whereNotExists(fn ($sub) => $sub->select(DB::raw(1))->from('subscriptions')->whereColumn('subscriptions.barbershop_id', 'barbershops.id'))
            ->update(['trial_ends_at' => now()->addDays($trialDays)]);

        PlatformSettings::set('features.billing', true);

        ActivityLog::record('billing.enabled', null, null, ['shops_given_trial' => $updated, 'trial_days' => $trialDays]);

        return $updated;
    }

    public function disableBilling(): void
    {
        PlatformSettings::set('features.billing', false);

        ActivityLog::record('billing.disabled');
    }

    public function billingEnabled(): bool
    {
        return Features::billing();
    }
}
