<?php

namespace Tests\Fakes;

use App\Services\Billing\BillingGateway;
use App\Services\Billing\BillingPayment;
use Carbon\CarbonImmutable;

/**
 * Betaalprovider in het geheugen voor tests: er gaat niets naar Mollie.
 */
class FakeBillingGateway implements BillingGateway
{
    /** @var array<string, BillingPayment> */
    public array $payments = [];

    /** @var list<array<string, mixed>> */
    public array $customers = [];

    /** @var list<array<string, mixed>> */
    public array $subscriptions = [];

    /** @var list<array{customer: string, id: string}> */
    public array $cancelled = [];

    /** @var list<array{customer: string, id: string, cents: int}> */
    public array $updated = [];

    /** @var list<string> */
    public array $fetched = [];

    public function createCustomer(string $name, string $email, array $metadata = []): string
    {
        $id = 'cst_'.(count($this->customers) + 1);
        $this->customers[] = compact('id', 'name', 'email', 'metadata');

        return $id;
    }

    public function createFirstPayment(string $customerId, int $amountCents, string $description, string $redirectUrl, ?string $webhookUrl, array $metadata = []): array
    {
        $id = 'tr_first'.(count($this->payments) + 1);
        $this->payments[$id] = new BillingPayment($id, 'open', $amountCents, $customerId, null, null, 'first');

        return ['id' => $id, 'checkout_url' => "https://www.mollie.com/checkout/select-method/{$id}"];
    }

    public function getPayment(string $paymentId): BillingPayment
    {
        $this->fetched[] = $paymentId;

        return $this->payments[$paymentId] ?? throw new \RuntimeException("Onbekende betaling {$paymentId}");
    }

    public function createSubscription(string $customerId, int $amountCents, string $description, CarbonImmutable $startDate, ?string $mandateId, ?string $webhookUrl, array $metadata = []): string
    {
        $id = 'sub_'.(count($this->subscriptions) + 1);
        $this->subscriptions[] = ['id' => $id, 'customer' => $customerId, 'cents' => $amountCents, 'start' => $startDate->toDateString(), 'mandate' => $mandateId];

        return $id;
    }

    public function cancelSubscription(string $customerId, string $subscriptionId): void
    {
        $this->cancelled[] = ['customer' => $customerId, 'id' => $subscriptionId];
    }

    public function updateSubscriptionAmount(string $customerId, string $subscriptionId, int $amountCents): void
    {
        $this->updated[] = ['customer' => $customerId, 'id' => $subscriptionId, 'cents' => $amountCents];
    }

    /**
     * Wat Mollie nu van een betaling zegt: zo laten we een betaling slagen, mislukken of binnenkomen.
     */
    public function set(string $id, string $status, string $sequenceType = 'first', ?string $customerId = null, ?string $subscriptionId = null, int $cents = 1000, ?string $mandateId = null): BillingPayment
    {
        return $this->payments[$id] = new BillingPayment(
            $id, $status, $cents, $customerId, $subscriptionId, $mandateId, $sequenceType,
            $status === 'paid' ? CarbonImmutable::now() : null,
        );
    }
}
