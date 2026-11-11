<?php

namespace App\Filament\Widgets;

use App\Services\PlatformStatsService;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MessagesOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Berichten deze maand';

    protected function getStats(): array
    {
        $stats = app(PlatformStatsService::class);
        $stats->ensureToday();

        $now = CarbonImmutable::now(PlatformStatsService::TIMEZONE);
        $month = fn (string $key): int => $stats->sum($key, $now->startOfMonth(), $now);

        return [
            Stat::make('E-mail', $month('messages_mail')),
            Stat::make('Push', $month('messages_push')),
            Stat::make('SMS', $month('messages_sms'))->description('Kosten '.money($month('message_cost_cents'))),
            Stat::make('WhatsApp', $month('messages_whatsapp')),
            Stat::make('Mislukt', $month('messages_failed'))->color($month('messages_failed') > 0 ? 'danger' : 'success'),
        ];
    }
}
