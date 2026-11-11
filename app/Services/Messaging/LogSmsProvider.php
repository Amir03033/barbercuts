<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Log;

/**
 * Standaard (lokaal en in tests): schrijft het bericht naar het log en kost niets.
 */
class LogSmsProvider implements SmsProvider
{
    public function send(string $toE164, string $message): ProviderResult
    {
        Log::info('SMS (log-provider)', ['to' => $toE164, 'message' => $message]);

        return ProviderResult::ok('log-'.bin2hex(random_bytes(4)), 0);
    }
}
