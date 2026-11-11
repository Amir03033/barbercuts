<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Log;

class LogWhatsAppProvider implements WhatsAppProvider
{
    public function sendTemplate(string $toE164, string $template, array $params): ProviderResult
    {
        Log::info('WhatsApp (log-provider)', ['to' => $toE164, 'template' => $template, 'params' => $params]);

        return ProviderResult::ok('log-'.bin2hex(random_bytes(4)), 0);
    }
}
