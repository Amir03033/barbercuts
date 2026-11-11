<?php

namespace App\Services\Billing;

use App\Enums\ShopRole;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BookingException;
use App\Models\ActivityLog;
use App\Models\Barbershop;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionPaymentFailed;
use App\Notifications\SubscriptionPriceChanged;
use App\Support\Features;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Abonnementen met Mollie (docs/BOUWOPDRACHT.md §7.7).
 *
 * Eerste betaling = één maand vooruit vanaf het einde van de proefperiode of gratis periode (of nu). Het
 * Mollie-abonnement begint pas na die maand, zodat er nooit dubbel wordt betaald.
 */
class SubscriptionService
{
    public function __construct(private BillingGateway $gateway) {}

    /**
     * Start de eerste betaling en geef de URL terug waar de eigenaar betaalt.
     */
    public function startCheckout(Barbershop $shop, User $owner): string
    {
        if (! Features::billing()) {
            throw new RuntimeException('Facturering staat uit.');
        }

        if ($shop->activeSubscription() !== null) {
            throw new BookingException('Je hebt al een lopend abonnement.');
        }

        $plan = $shop->plan ?? Plan::current();

        if ($plan === null || ! $plan->is_active) {
            throw new BookingException('Er is op dit moment geen abonnement beschikbaar. Probeer het later opnieuw.');
        }

        $customerId = $shop->mollie_customer_id ?? $this->gateway->createCustomer(
            $shop->name,
            $owner->email,
            ['barbershop_id' => $shop->id],
        );

        // De betaalde maand gaat in na de proefperiode of gratis periode; is er geen, dan nu.
        $periodStart = collect([$shop->trial_ends_at, $shop->free_until, now()])
            ->filter()->max()->toImmutable();

        $subscription = DB::transaction(function () use ($shop, $plan, $customerId, $periodStart): Subscription {
            // Een nooit betaalde poging geeft geen toegang: geen betaalde periode.
            $shop->subscriptions()->where('status', SubscriptionStatus::Pending->value)->update([
                'status' => SubscriptionStatus::Cancelled->value,
                'cancelled_at' => now(),
                'current_period_ends_at' => null,
            ]);

            // Een verlopen zaak is alleen-lezen, maar moet wél kunnen betalen: dit slaat de controle op alleen-lezen over.
            Barbershop::withoutEvents(fn () => $shop->forceFill(['mollie_customer_id' => $customerId, 'plan_id' => $plan->id])->save());

            $subscription = new Subscription;
            $subscription->barbershop_id = $shop->id;
            $subscription->plan_id = $plan->id;
            $subscription->price_cents = $plan->price_cents;
            $subscription->current_period_ends_at = Carbon::instance($periodStart->addMonth());
            $subscription->save();

            return $subscription;
        });

        $payment = $this->gateway->createFirstPayment(
            $customerId,
            $plan->price_cents,
            "BarberCuts abonnement {$shop->name}",
            route('app.subscription', ['terug' => 1]),
            $this->webhookUrl(),
            ['barbershop_id' => $shop->id, 'subscription_id' => $subscription->id],
        );

        $record = new Payment;
        $record->barbershop_id = $shop->id;
        $record->payable()->associate($subscription);
        $record->mollie_payment_id = $payment['id'];
        $record->amount_cents = $plan->price_cents;
        $record->status = 'open';
        $record->metadata = ['sequence_type' => 'first'];
        $record->save();

        return $payment['checkout_url'];
    }

    /**
     * Verwerk een webhook van Mollie. De status wordt ALTIJD opnieuw bij Mollie opgehaald; de payload wordt niet vertrouwd.
     */
    public function handlePaymentWebhook(string $paymentId): void
    {
        $remote = $this->gateway->getPayment($paymentId);

        DB::transaction(function () use ($remote): void {
            $record = Payment::query()->where('mollie_payment_id', $remote->id)->lockForUpdate()->first();
            $subscription = $record !== null
                ? $this->subscriptionOf($record)
                : ($remote->subscriptionId !== null ? Subscription::query()->where('mollie_subscription_id', $remote->subscriptionId)->first() : null);

            if ($subscription === null) {
                Log::warning('Mollie-webhook voor een onbekende betaling', ['payment' => $remote->id]);

                return;
            }

            $wasPaid = $record?->status === 'paid';

            if ($record === null) {
                $record = new Payment;
                $record->barbershop_id = $subscription->barbershop_id;
                $record->payable()->associate($subscription);
                $record->mollie_payment_id = $remote->id;
                $record->amount_cents = $remote->amountCents;
                $record->metadata = ['sequence_type' => $remote->sequenceType];
            }

            $record->status = $remote->status;
            $record->paid_at = $remote->isPaid() ? ($remote->paidAt ?? now()) : $record->paid_at;
            $record->save();

            // Dezelfde webhook kan vaker binnenkomen: een betaling telt maar één keer.
            if ($remote->isPaid() && ! $wasPaid) {
                $this->onPaid($subscription, $remote);
            } elseif ($remote->isFailed()) {
                $this->onFailed($subscription, $remote);
            }
        });
    }

    /**
     * Opzeggen: de toegang blijft tot het einde van de betaalde periode (§7.7, punt 6).
     */
    public function cancel(Subscription $subscription): void
    {
        $shop = Barbershop::findOrFail($subscription->barbershop_id);

        if ($subscription->mollie_subscription_id !== null && $shop->mollie_customer_id !== null) {
            $this->gateway->cancelSubscription($shop->mollie_customer_id, $subscription->mollie_subscription_id);
        }

        $subscription->forceFill(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()])->save();

        ActivityLog::record('subscription.cancelled', $shop, $subscription);
    }

    /**
     * Prijs van een plan wijzigen. Bestaande abonnementen houden hun vastgelegde prijs, tenzij $applyToExisting (§7.7, punt 5).
     */
    public function changePrice(Plan $plan, int $newCents, bool $applyToExisting): int
    {
        $plan->forceFill(['price_cents' => $newCents])->save();

        if (! $applyToExisting) {
            return 0;
        }

        $changed = 0;

        $plan->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->where('price_cents', '!=', $newCents)
            ->each(function (Subscription $subscription) use ($newCents, &$changed): void {
                $shop = Barbershop::findOrFail($subscription->barbershop_id);

                if ($subscription->mollie_subscription_id !== null && $shop->mollie_customer_id !== null) {
                    $this->gateway->updateSubscriptionAmount($shop->mollie_customer_id, $subscription->mollie_subscription_id, $newCents);
                }

                $old = $subscription->price_cents;
                $subscription->forceFill(['price_cents' => $newCents])->save();

                foreach ($this->owners($shop) as $owner) {
                    $owner->notify(new SubscriptionPriceChanged($shop->id, $old, $newCents));
                }

                $changed++;
            });

        return $changed;
    }

    private function onPaid(Subscription $subscription, BillingPayment $remote): void
    {
        $shop = Barbershop::findOrFail($subscription->barbershop_id);

        if ($remote->sequenceType === 'first') {
            if ($subscription->status !== SubscriptionStatus::Pending) {
                return;
            }

            Barbershop::withoutEvents(fn () => $shop->forceFill(['mollie_mandate_id' => $remote->mandateId ?? $shop->mollie_mandate_id])->save());

            // Het eerste Mollie-abonnement-betaling is pas na de betaalde maand (nooit dubbel betalen).
            $subscription->mollie_subscription_id = $this->gateway->createSubscription(
                (string) $shop->mollie_customer_id,
                $subscription->price_cents,
                "BarberCuts abonnement {$shop->name}",
                CarbonImmutable::instance($subscription->current_period_ends_at),
                $shop->mollie_mandate_id,
                $this->webhookUrl(),
                ['barbershop_id' => $shop->id, 'subscription_id' => $subscription->id],
            );
            $subscription->status = SubscriptionStatus::Active;
            $subscription->save();

            return;
        }

        // Terugkerende betaling: een maand erbij en geen achterstand meer.
        $from = $subscription->current_period_ends_at?->isFuture() ? $subscription->current_period_ends_at : now();

        $subscription->forceFill([
            'status' => SubscriptionStatus::Active,
            'past_due_at' => null,
            'current_period_ends_at' => $from->copy()->addMonth(),
        ])->save();
    }

    private function onFailed(Subscription $subscription, BillingPayment $remote): void
    {
        if ($remote->sequenceType === 'first') {
            if ($subscription->status === SubscriptionStatus::Pending) {
                $subscription->forceFill(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now(), 'current_period_ends_at' => null])->save();
            }

            return;
        }

        if ($subscription->status === SubscriptionStatus::PastDue) {
            return;
        }

        $subscription->forceFill(['status' => SubscriptionStatus::PastDue, 'past_due_at' => $subscription->past_due_at ?? now()])->save();

        $shop = Barbershop::findOrFail($subscription->barbershop_id);

        foreach ($this->owners($shop) as $owner) {
            $owner->notify(new SubscriptionPaymentFailed($shop->id));
        }
    }

    private function subscriptionOf(Payment $payment): ?Subscription
    {
        return $payment->payable_type === Subscription::class ? Subscription::query()->find($payment->payable_id) : null;
    }

    /**
     * @return iterable<int, User>
     */
    private function owners(Barbershop $shop): iterable
    {
        return $shop->users()->wherePivot('role', ShopRole::Owner->value)->get();
    }

    /**
     * Mollie kan alleen een publiek bereikbare https-URL aanroepen; lokaal laten we de webhook weg.
     */
    private function webhookUrl(): ?string
    {
        $url = route('webhooks.mollie');

        return str_starts_with($url, 'https://') ? $url : null;
    }
}
