<?php

namespace App\Console\Commands;

use App\Enums\NotificationStatus;
use App\Models\AppointmentNotification;
use App\Services\Notifications\NotificationDeliverer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Elke minuut door de scheduler: verstuurt alle berichten waarvan `send_at` is aangebroken (§7.6).
 */
#[Signature('notifications:send-due {--limit=500 : Maximaal aantal berichten per run}')]
#[Description('Verstuur de geplande herinneringen en berichten die aan de beurt zijn')]
class SendDueNotifications extends Command
{
    public function handle(NotificationDeliverer $deliverer): int
    {
        // Een rij die langer dan een half uur op 'sending' staat is onderbroken. Niet opnieuw proberen: dat kan dubbel versturen.
        AppointmentNotification::query()
            ->where('status', NotificationStatus::Sending->value)
            ->where('updated_at', '<', now()->subMinutes(30))
            ->update(['status' => NotificationStatus::Failed->value, 'error' => 'Verzending onderbroken.']);

        $counts = [];

        AppointmentNotification::query()
            ->where('status', NotificationStatus::Pending->value)
            ->where('send_at', '<=', now())
            ->orderBy('send_at')
            ->limit((int) $this->option('limit'))
            ->pluck('id')
            ->each(function (int $id) use ($deliverer, &$counts): void {
                $status = $deliverer->deliver($id);

                if ($status !== null) {
                    $counts[$status->value] = ($counts[$status->value] ?? 0) + 1;
                }
            });

        $this->info($counts === [] ? 'Niets te versturen.' : collect($counts)->map(fn (int $count, string $status): string => "{$status}: {$count}")->implode(', '));

        return self::SUCCESS;
    }
}
