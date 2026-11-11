<?php

namespace App\Http\Controllers;

use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook van Mollie (§7.7). Mollie stuurt alleen een betalings-id; wij halen de status zelf op bij Mollie.
 */
class MollieWebhookController extends Controller
{
    public function __invoke(Request $request, SubscriptionService $subscriptions): Response
    {
        $paymentId = (string) $request->input('id');

        // Alleen een geldig betalings-id doorgeven; voor al het andere geven we Mollie geen informatie terug.
        if (preg_match('/^tr_[A-Za-z0-9]+$/', $paymentId) === 1) {
            $subscriptions->handlePaymentWebhook($paymentId);
        }

        return response('', 200);
    }
}
