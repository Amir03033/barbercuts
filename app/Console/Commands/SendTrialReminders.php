<?php

namespace App\Console\Commands;

use App\Services\Billing\TrialReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Elke ochtend door de scheduler: herinnert eigenaren aan het einde van hun proefperiode.
 */
#[Signature('billing:remind-trials')]
#[Description('Mail eigenaren wiens proefperiode bijna voorbij of net verlopen is')]
class SendTrialReminders extends Command
{
    public function handle(TrialReminderService $reminders): int
    {
        $this->info($reminders->sendDue().' bericht(en) verstuurd.');

        return self::SUCCESS;
    }
}
