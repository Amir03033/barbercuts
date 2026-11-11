<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WhatsApp via Twilio met een goedgekeurde template (Content SID). $template is de Content SID (HX...).
 * Instellen: TWILIO_SID, TWILIO_TOKEN en TWILIO_WHATSAPP_FROM (whatsapp:+31...). Kosten: WHATSAPP_COST_CENTS.
 */
class TwilioWhatsAppProvider implements WhatsAppProvider
{
    public function sendTemplate(string $toE164, string $template, array $params): ProviderResult
    {
        $variables = [];

        foreach ($params as $index => $value) {
            $variables[(string) ($index + 1)] = $value;
        }

        try {
            $response = Http::withBasicAuth((string) config('services.twilio.sid'), (string) config('services.twilio.token'))
                ->asForm()->timeout(10)
                ->post('https://api.twilio.com/2010-04-01/Accounts/'.config('services.twilio.sid').'/Messages.json', [
                    'To' => 'whatsapp:'.$toE164,
                    'From' => (string) config('services.twilio.whatsapp_from'),
                    'ContentSid' => $template,
                    'ContentVariables' => json_encode($variables, JSON_THROW_ON_ERROR),
                ]);
        } catch (Throwable $exception) {
            return ProviderResult::failed('Twilio niet bereikbaar: '.$exception->getMessage());
        }

        if (! $response->successful()) {
            return ProviderResult::failed('Twilio: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return ProviderResult::ok((string) $response->json('sid'), (int) config('barbercuts.whatsapp.cost_cents'));
    }
}
