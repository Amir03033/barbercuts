<?php

namespace App\Console\Commands;

use App\Models\Barbershop;
use App\Services\WinbackService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Elke ochtend door de scheduler: stuurt klanten die te lang niet zijn geweest een bericht (fase 8).
 */
#[Signature('winback:send')]
#[Description('Verstuur de berichten "klanten terughalen" van vandaag')]
class SendWinbackMessages extends Command
{
    public function handle(WinbackService $winback): int
    {
        $sent = 0;

        Barbershop::query()->real()
            ->whereExists(fn ($sub) => $sub->selectRaw('1')->from('shop_settings')->whereColumn('shop_settings.barbershop_id', 'barbershops.id')->whereNotNull('winback_weeks'))
            ->each(function (Barbershop $shop) use ($winback, &$sent): void {
                $sent += $winback->sendFor($shop);
            });

        $this->info($sent.' bericht(en) verstuurd.');

        return self::SUCCESS;
    }
}
