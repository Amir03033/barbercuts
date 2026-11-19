<?php

namespace App\Services\Billing;

use App\Enums\AccessState;
use App\Enums\ShopRole;
use App\Models\Barbershop;
use App\Notifications\TrialEnding;
use App\Support\Features;

/**
 * Stuurt de eigenaar een e-mail 3 dagen en 1 dag voor het einde van de proefperiode, en één keer als die verlopen is.
 * Elk bericht gaat per proefperiode maar één keer weg (onthouden in barbershops.billing_notices).
 */
class TrialReminderService
{
    /**
     * @return int aantal verstuurde berichten (per eigenaar)
     */
    public function sendDue(): int
    {
        if (! Features::billing()) {
            return 0;
        }

        $sent = 0;

        Barbershop::query()->real()->whereNull('suspended_at')->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now()->subDays(3))
            ->where('trial_ends_at', '<', now()->addDays(4))
            ->each(function (Barbershop $shop) use (&$sent): void {
                $stage = $this->stageFor($shop);

                if ($stage === null) {
                    return;
                }

                $key = $stage.':'.$shop->trial_ends_at->toDateString();
                $notices = (array) $shop->billing_notices;

                if (in_array($key, $notices, true)) {
                    return;
                }

                foreach ($shop->users()->wherePivot('role', ShopRole::Owner->value)->get() as $owner) {
                    $owner->notify(new TrialEnding($shop->id, $stage));
                    $sent++;
                }

                $shop->forceFill(['billing_notices' => [...$notices, $key]])->save();
            });

        return $sent;
    }

    private function stageFor(Barbershop $shop): ?string
    {
        $state = $shop->accessState();

        if ($state === AccessState::Expired) {
            return TrialEnding::EXPIRED;
        }

        if ($state !== AccessState::Trial) {
            return null;
        }

        $days = $shop->trialDaysLeft();

        return match (true) {
            $days === null => null,
            $days <= 1 => TrialEnding::ONE_DAY,
            $days <= 3 => TrialEnding::THREE_DAYS,
            default => null,
        };
    }
}
