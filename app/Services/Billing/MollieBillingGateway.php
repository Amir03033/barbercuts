<?php

namespace App\Services\Billing;

use BackedEnum;
use Carbon\CarbonImmutable;
use Mollie\Api\MollieApiClient;

/**
 * Mollie-implementatie van BillingGateway (mollie/laravel-mollie). De API-sleutel staat in MOLLIE_KEY.
 */
class MollieBillingGateway implements BillingGateway
{
    public function __construct(private MollieApiClient $mollie) {}

    public function createCustomer(string $name, string $email, array $metadata = []): string
    {
        return $this->mollie->customers->create(['name' => $name, 'email' => $email, 'metadata' => $metadata])->id;
    }

    public function createFirstPayment(string $customerId, int $amountCents, string $description, string $redirectUrl, ?string $webhookUrl, array $metadata = []): array
    {
        $payment = $this->mollie->customerPayments->createForId($customerId, array_filter([
            'amount' => $this->amount($amountCents),
            'description' => $description,
            'sequenceType' => 'first',
            'method' => 'ideal',
            'redirectUrl' => $redirectUrl,
            'webhookUrl' => $webhookUrl,
            'metadata' => $metadata,
        ], fn (mixed $value): bool => $value !== null));

        return ['id' => $payment->id, 'checkout_url' => (string) $payment->getCheckoutUrl()];
    }

    public function getPayment(string $paymentId): BillingPayment
    {
        $payment = $this->mollie->payments->get($paymentId);

        return new BillingPayment(
            id: $payment->id,
            status: $this->value($payment->status),
            amountCents: (int) round(((float) $payment->amount->value) * 100),
            customerId: $payment->customerId,
            subscriptionId: $payment->subscriptionId,
            mandateId: $payment->mandateId,
            sequenceType: $this->value($payment->sequenceType ?? 'oneoff'),
            paidAt: $payment->paidAt !== null ? CarbonImmutable::parse($payment->paidAt) : null,
        );
    }

    public function createSubscription(string $customerId, int $amountCents, string $description, CarbonImmutable $startDate, ?string $mandateId, ?string $webhookUrl, array $metadata = []): string
    {
        return $this->mollie->subscriptions->createForId($customerId, array_filter([
            'amount' => $this->amount($amountCents),
            'interval' => '1 month',
            'startDate' => $startDate->toDateString(),
            'description' => $description,
            'mandateId' => $mandateId,
            'webhookUrl' => $webhookUrl,
            'metadata' => $metadata,
        ], fn (mixed $value): bool => $value !== null))->id;
    }

    public function cancelSubscription(string $customerId, string $subscriptionId): void
    {
        $this->mollie->subscriptions->cancelForId($customerId, $subscriptionId);
    }

    public function updateSubscriptionAmount(string $customerId, string $subscriptionId, int $amountCents): void
    {
        $this->mollie->subscriptions->update($customerId, $subscriptionId, ['amount' => $this->amount($amountCents)]);
    }

    /**
     * @return array{currency: string, value: string}
     */
    private function amount(int $cents): array
    {
        return ['currency' => 'EUR', 'value' => number_format($cents / 100, 2, '.', '')];
    }

    private function value(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
