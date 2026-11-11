<?php

namespace App\Services\Billing;

use Carbon\CarbonImmutable;

/**
 * Een betaling zoals de betaalprovider hem nu kent (altijd vers opgehaald, nooit uit een webhook-payload).
 */
final readonly class BillingPayment
{
    public function __construct(
        public string $id,
        /** open | pending | authorized | paid | failed | canceled | expired */
        public string $status,
        public int $amountCents,
        public ?string $customerId = null,
        public ?string $subscriptionId = null,
        public ?string $mandateId = null,
        /** oneoff | first | recurring */
        public string $sequenceType = 'oneoff',
        public ?CarbonImmutable $paidAt = null,
    ) {}

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'canceled', 'expired'], true);
    }
}
