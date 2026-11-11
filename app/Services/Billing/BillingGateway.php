<?php

namespace App\Services\Billing;

use Carbon\CarbonImmutable;

/**
 * Wat de applicatie van de betaalprovider (Mollie) nodig heeft voor abonnementen (§7.7).
 * Tests gebruiken een eigen implementatie; de echte staat in MollieBillingGateway.
 */
interface BillingGateway
{
    /**
     * @param  array<string, scalar>  $metadata
     * @return string id van de klant bij de provider
     */
    public function createCustomer(string $name, string $email, array $metadata = []): string;

    /**
     * Eerste betaling (sequenceType "first", iDEAL): na betaling bestaat er een mandaat voor de incasso's.
     *
     * @param  array<string, scalar>  $metadata
     * @return array{id: string, checkout_url: string}
     */
    public function createFirstPayment(string $customerId, int $amountCents, string $description, string $redirectUrl, ?string $webhookUrl, array $metadata = []): array;

    public function getPayment(string $paymentId): BillingPayment;

    /**
     * @param  array<string, scalar>  $metadata
     * @return string id van het abonnement bij de provider
     */
    public function createSubscription(string $customerId, int $amountCents, string $description, CarbonImmutable $startDate, ?string $mandateId, ?string $webhookUrl, array $metadata = []): string;

    public function cancelSubscription(string $customerId, string $subscriptionId): void;

    public function updateSubscriptionAmount(string $customerId, string $subscriptionId, int $amountCents): void;
}
