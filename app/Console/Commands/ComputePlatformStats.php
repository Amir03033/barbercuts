<?php

namespace App\Console\Commands;

use App\Services\PlatformStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Berekent de platformstatistiek voor het adminpanel (dagelijks via de scheduler, zie routes/console.php).
 */
#[Signature('stats:compute {--days=2 : Aantal dagen terug (inclusief vandaag), voor het aanvullen van geschiedenis}')]
#[Description('Bereken de dagelijkse platformstatistiek')]
class ComputePlatformStats extends Command
{
    public function handle(PlatformStatsService $stats): int
    {
        $days = max(1, min(366, (int) $this->option('days')));
        $today = CarbonImmutable::now(PlatformStatsService::TIMEZONE);

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $stats->compute($today->subDays($offset));
        }

        $this->info("Statistiek berekend voor {$days} dag(en).");

        return self::SUCCESS;
    }
}
