<?php

namespace App\Services\Messaging;

interface WhatsAppProvider
{
    /**
     * WhatsApp werkt met door Meta goedgekeurde templates; $params zijn de variabelen in volgorde (1, 2, 3, ...).
     *
     * @param  list<string>  $params
     */
    public function sendTemplate(string $toE164, string $template, array $params): ProviderResult;
}
