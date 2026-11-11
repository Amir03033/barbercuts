<?php

namespace App\Services\Messaging;

interface SmsProvider
{
    public function send(string $toE164, string $message): ProviderResult;
}
