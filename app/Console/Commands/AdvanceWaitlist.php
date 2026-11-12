<?php

namespace App\Console\Commands;

use App\Services\WaitlistService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Elke minuut door de scheduler: biedt vrijgekomen plekken aan de volgende op de wachtlijst aan (fase 8).
 */
#[Signature('waitlist:advance')]
#[Description('Bied vrijgekomen plekken aan de wachtlijst aan')]
class AdvanceWaitlist extends Command
{
    public function handle(WaitlistService $waitlist): int
    {
        $this->info($waitlist->advanceAll().' plek(ken) aangeboden.');

        return self::SUCCESS;
    }
}
