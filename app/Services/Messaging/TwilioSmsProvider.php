<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * SMS via de Twilio Messages API (tijdelijke keuze: de eigenaar heeft nog geen provider gekozen).
 * Instellen: TWILIO_SID, TWILIO_TOKEN en TWILIO_SMS_FROM (nummer of afzendernaam). Kosten: SMS_COST_CENTS per bericht.
 */
class TwilioSmsProvider implements SmsProvider
{
    public function send(string $toE164, string $message): ProviderResult
    {
        try {
            $response = Http::withBasicAuth((string) config('services.twilio.sid'), (string) config('services.twilio.token'))
                ->asForm()->timeout(10)
                ->post('https://api.twilio.com/2010-04-01/Accounts/'.config('services.twilio.sid').'/Messages.json', [
                    'To' => $toE164,
                    'From' => (string) config('services.twilio.sms_from'),
                    'Body' => $message,
                ]);
        } catch (Throwable $exception) {
            return ProviderResult::failed('Twilio niet bereikbaar: '.$exception->getMessage());
        }

        if (! $response->successful()) {
            return ProviderResult::failed('Twilio: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return ProviderResult::ok((string) $response->json('sid'), (int) config('barbercuts.sms.cost_cents'));
    }
}
