<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoShopService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Elk uur door de scheduler: wist demozaken van pitchers waarvan de tijd om is (fase 6).
 */
#[Signature('demo:cleanup')]
#[Description('Wis verlopen demozaken van pitchers')]
class CleanupDemoShops extends Command
{
    public function handle(DemoShopService $demos): int
    {
        $this->info($demos->deleteExpired().' demozaak/-zaken gewist.');

        return self::SUCCESS;
    }
}
