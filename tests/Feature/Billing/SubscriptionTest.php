<?php

use App\Enums\AccessState;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BookingException;
use App\Exceptions\ShopReadOnlyException;
use App\Livewire\App\Subscription as SubscriptionPage;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\SubscriptionPaymentFailed;
use App\Notifications\SubscriptionPriceChanged;
use App\Services\Billing\BillingGateway;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Fakes\FakeBillingGateway;

/*
| Mollie-abonnementen (docs/BOUWOPDRACHT.md §7.7), met een nagebootste betaalprovider. "Nu" is dinsdag 17 november 2026, 10:00.
*/

beforeEach(function () {
    Notification::fake();
    $this->travelTo(at('2026-11-17 10:00'));
    billingOn();

    $this->gateway = new FakeBillingGateway;
    app()->instance(BillingGateway::class, $this->gateway);
    $this->service = app(SubscriptionService::class);

    [$this->shop, $this->owner] = shopInState('expired');

    // Een betaling "binnen laten komen" zoals Mollie de webhook aanroept: alleen met een id.
    $this->webhook = fn (string $id) => $this->post('/webhooks/mollie', ['id' => $id])->assertOk();
});

it('starts a checkout for an expired shop with a first payment of one month from now', function () {
    $url = $this->service->startCheckout($this->shop, $this->owner);

    $subscription = Subscription::query()->firstOrFail();
    $payment = Payment::query()->firstOrFail();

    expect($url)->toStartWith('https://www.mollie.com/checkout/')
        ->and($this->gateway->customers)->toHaveCount(1)
        ->and($this->gateway->customers[0]['email'])->toBe($this->owner->email)
        ->and($this->shop->fresh()->mollie_customer_id)->toBe('cst_1')
        ->and($subscription->status)->toBe(SubscriptionStatus::Pending)
        ->and($subscription->price_cents)->toBe(1000)
        ->and($subscription->current_period_ends_at->toDateString())->toBe('2026-12-17')
        ->and($payment->amount_cents)->toBe(1000)->and($payment->status)->toBe('open')
        ->and($payment->payable_id)->toBe($subscription->id)
        // Nog niet betaald: geen toegang
        ->and($this->shop->fresh()->accessState())->toBe(AccessState::Expired);
});

it('lets the first paid month start after the trial so nobody pays twice', function () {
    [$shop, $owner] = shopInState('trial');   // proefperiode tot 27 november

    $this->service->startCheckout($shop, $owner);
    $subscription = Subscription::query()->where('barbershop_id', $shop->id)->firstOrFail();

    expect($subscription->current_period_ends_at->toDateString())->toBe('2026-12-27');

    ($this->webhook)($this->gateway->set('tr_first1', 'paid', 'first', 'cst_1', null, 1000, 'mdt_1')->id);

    // Het Mollie-abonnement begint pas na de betaalde maand; tot dan loopt de proefperiode gewoon door.
    expect($this->gateway->subscriptions)->toHaveCount(1)
        ->and($this->gateway->subscriptions[0]['start'])->toBe('2026-12-27')
        ->and($shop->fresh()->accessState())->toBe(AccessState::Active);
});

it('activates the subscription when the first payment is paid, fetching the status from Mollie', function () {
    $this->service->startCheckout($this->shop, $this->owner);
    $this->gateway->set('tr_first1', 'paid', 'first', 'cst_1', null, 1000, 'mdt_9');

    ($this->webhook)('tr_first1');

    $subscription = Subscription::query()->firstOrFail();

    expect($this->gateway->fetched)->toBe(['tr_first1'])
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->mollie_subscription_id)->toBe('sub_1')
        ->and($this->shop->fresh()->mollie_mandate_id)->toBe('mdt_9')
        ->and($this->gateway->subscriptions[0])->toMatchArray(['customer' => 'cst_1', 'cents' => 1000, 'start' => '2026-12-17', 'mandate' => 'mdt_9'])
        ->and(Payment::query()->firstOrFail()->status)->toBe('paid')
        ->and(Payment::query()->firstOrFail()->paid_at)->not->toBeNull()
        ->and($this->shop->fresh()->accessState())->toBe(AccessState::Active);
});

it('never trusts the webhook payload: the status always comes from Mollie', function () {
    $this->service->startCheckout($this->shop, $this->owner);
    // Mollie zegt: nog open. De aanroeper beweert dat het betaald is.
    $this->post('/webhooks/mollie', ['id' => 'tr_first1', 'status' => 'paid', 'amount' => ['value' => '10.00']])->assertOk();

    expect(Subscription::query()->firstOrFail()->status)->toBe(SubscriptionStatus::Pending)
        ->and($this->shop->fresh()->accessState())->toBe(AccessState::Expired)
        ->and($this->gateway->subscriptions)->toBe([]);
});

it('handles the same webhook more than once without paying twice or creating two subscriptions', function () {
    $this->service->startCheckout($this->shop, $this->owner);
    $this->gateway->set('tr_first1', 'paid', 'first', 'cst_1', null, 1000, 'mdt_1');

    ($this->webhook)('tr_first1');
    ($this->webhook)('tr_first1');
    ($this->webhook)('tr_first1');

    expect($this->gateway->subscriptions)->toHaveCount(1)
        ->and(Subscription::query()->firstOrFail()->current_period_ends_at->toDateString())->toBe('2026-12-17')
        ->and(Payment::query()->count())->toBe(1);
});

it('cancels the pending subscription when the first payment fails', function (string $status) {
    $this->service->startCheckout($this->shop, $this->owner);
    $this->gateway->set('tr_first1', $status, 'first', 'cst_1');

    ($this->webhook)('tr_first1');

    expect(Subscription::query()->firstOrFail()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and(Payment::query()->firstOrFail()->status)->toBe($status)
        ->and($this->shop->fresh()->accessState())->toBe(AccessState::Expired);

    // Opnieuw proberen kan
    expect($this->service->startCheckout($this->shop->fresh(), $this->owner))->toStartWith('https://');
})->with(['failed', 'canceled', 'expired']);

it('lets a customer start over with a new checkout and cancels the abandoned one', function () {
    $this->service->startCheckout($this->shop, $this->owner);
    $this->service->startCheckout($this->shop->fresh(), $this->owner);

    expect(Subscription::query()->orderBy('id')->pluck('status')->map->value->all())->toBe(['cancelled', 'pending'])
        ->and($this->gateway->customers)->toHaveCount(1);   // dezelfde Mollie-klant
});

it('extends the period with every recurring payment and clears a payment problem', function () {
    $subscription = subscriptionFor($this->shop, 'past_due', ['mollie_subscription_id' => 'sub_x', 'past_due_at' => now()->subDay(), 'current_period_ends_at' => now()->addDays(5)]);
    $this->shop->forceFill(['mollie_customer_id' => 'cst_1'])->save();
    $this->gateway->set('tr_rec1', 'paid', 'recurring', 'cst_1', 'sub_x');

    ($this->webhook)('tr_rec1');
    ($this->webhook)('tr_rec1');   // dezelfde betaling telt één keer

    $fresh = $subscription->fresh();

    expect($fresh->status)->toBe(SubscriptionStatus::Active)
        ->and($fresh->past_due_at)->toBeNull()
        ->and($fresh->current_period_ends_at->toDateString())->toBe('2026-12-22')   // 5 dagen resterend + 1 maand
        ->and(Payment::query()->where('mollie_payment_id', 'tr_rec1')->count())->toBe(1)
        ->and(Payment::query()->firstOrFail()->barbershop_id)->toBe($this->shop->id);
});

it('sets past due and mails the owner when a recurring payment fails', function () {
    subscriptionFor($this->shop, 'active', ['mollie_subscription_id' => 'sub_x']);
    $this->gateway->set('tr_rec1', 'failed', 'recurring', 'cst_1', 'sub_x');

    ($this->webhook)('tr_rec1');
    ($this->webhook)('tr_rec1');

    $subscription = Subscription::query()->firstOrFail();

    expect($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->past_due_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($this->shop->fresh()->accessState())->toBe(AccessState::PastDue);

    Notification::assertSentToTimes($this->owner, SubscriptionPaymentFailed::class, 1);

    // Na 7 dagen zonder betaling is de zaak verlopen
    $this->travelTo(now()->addDays(8));
    expect($this->shop->fresh()->accessState())->toBe(AccessState::Expired);
});

it('ignores webhooks for unknown payments and malformed ids', function () {
    $this->gateway->set('tr_other', 'paid', 'recurring', 'cst_9', 'sub_unknown');

    ($this->webhook)('tr_other');
    ($this->webhook)('abc; drop table');
    ($this->webhook)('');
    $this->post('/webhooks/mollie')->assertOk();

    expect(Payment::query()->count())->toBe(0)
        // Het ongeldige id wordt niet eens doorgegeven aan Mollie
        ->and($this->gateway->fetched)->toBe(['tr_other']);
});

it('cancels at Mollie and keeps access until the end of the paid period', function () {
    $subscription = subscriptionFor($this->shop, 'active', ['mollie_subscription_id' => 'sub_x', 'current_period_ends_at' => now()->addDays(20)]);
    $this->shop->forceFill(['mollie_customer_id' => 'cst_1'])->save();

    $this->service->cancel($subscription);

    expect($this->gateway->cancelled)->toBe([['customer' => 'cst_1', 'id' => 'sub_x']])
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($subscription->fresh()->cancelled_at)->not->toBeNull()
        ->and($this->shop->fresh()->accessState())->toBe(AccessState::Active);

    $this->travelTo(now()->addDays(21));
    expect($this->shop->fresh()->accessState())->toBe(AccessState::Expired);
});

it('changes the price for new subscriptions only, unless the admin chooses existing ones too', function () {
    $plan = Plan::current();
    $mine = subscriptionFor($this->shop, 'active', ['plan_id' => $plan->id, 'mollie_subscription_id' => 'sub_x']);
    $this->shop->forceFill(['mollie_customer_id' => 'cst_1'])->save();

    expect($this->service->changePrice($plan, 1250, applyToExisting: false))->toBe(0);

    expect($plan->fresh()->price_cents)->toBe(1250)
        ->and($mine->fresh()->price_cents)->toBe(1000)     // houdt de vastgelegde prijs
        ->and($this->gateway->updated)->toBe([]);
    Notification::assertNothingSent();

    expect($this->service->changePrice($plan, 1500, applyToExisting: true))->toBe(1)
        ->and($mine->fresh()->price_cents)->toBe(1500)
        ->and($this->gateway->updated)->toBe([['customer' => 'cst_1', 'id' => 'sub_x', 'cents' => 1500]]);

    Notification::assertSentTo($this->owner, SubscriptionPriceChanged::class, fn (SubscriptionPriceChanged $n) => $n->oldCents === 1000 && $n->newCents === 1500);
});

it('uses the new price for a subscription that starts after a change', function () {
    Plan::current()->update(['price_cents' => 1400]);

    $this->service->startCheckout($this->shop, $this->owner);

    expect(Subscription::query()->firstOrFail()->price_cents)->toBe(1400)
        ->and(Payment::query()->firstOrFail()->amount_cents)->toBe(1400);
});

it('refuses a checkout while billing is off or when a subscription is already running', function () {
    subscriptionFor($this->shop, 'active');

    expect(fn () => $this->service->startCheckout($this->shop, $this->owner))->toThrow(BookingException::class, 'lopend abonnement');

    config(['barbercuts.billing.enabled' => false]);
    expect(fn () => $this->service->startCheckout($this->shop, $this->owner))->toThrow(RuntimeException::class);
});

it('lets an expired shop pay even though everything else is read-only', function () {
    actingInShop($this->owner, $this->shop);

    $url = $this->service->startCheckout($this->shop, $this->owner);

    expect($url)->toStartWith('https://')->and($this->shop->fresh()->mollie_customer_id)->toBe('cst_1');

    // ...maar een gewone wijziging blijft geweigerd
    expect(fn () => $this->shop->update(['accent_color' => '#ff0000']))->toThrow(ShopReadOnlyException::class);
});

it('refuses a checkout when there is no active plan', function () {
    Plan::query()->update(['is_active' => false]);

    expect(fn () => $this->service->startCheckout($this->shop, $this->owner))->toThrow(BookingException::class);
});

// ── Het scherm voor de eigenaar ──────────────────────────────────────────────────────────────────────────

it('shows the owner the status and price and sends him to Mollie to pay', function () {
    actingInShop($this->owner, $this->shop);

    Livewire::test(SubscriptionPage::class)
        ->assertSee('Verlopen')->assertSee('€ 10,00')->assertSee('per maand')
        ->assertSee('Abonnement starten')
        ->call('start')
        ->assertRedirect('https://www.mollie.com/checkout/select-method/tr_first1');
});

it('explains that the paid month starts after the trial', function () {
    [$shop, $owner] = shopInState('trial');
    actingInShop($owner, $shop);

    Livewire::test(SubscriptionPage::class)->assertSee('Proefperiode')->assertSee('dus je betaalt nooit dubbel');
});

it('lets the owner cancel a running subscription from the screen', function () {
    $subscription = subscriptionFor($this->shop, 'active', ['mollie_subscription_id' => 'sub_x']);
    $this->shop->forceFill(['mollie_customer_id' => 'cst_1'])->save();
    actingInShop($this->owner, $this->shop);

    Livewire::test(SubscriptionPage::class)->assertSee('Betaald tot')->assertSee('Abonnement opzeggen')->call('cancel')->assertDispatched('toast');

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled);

    Livewire::test(SubscriptionPage::class)->assertSee('Opgezegd');
});

it('lists the payments with a human status', function () {
    $this->service->startCheckout($this->shop, $this->owner);
    $this->gateway->set('tr_first1', 'paid', 'first', 'cst_1', null, 1000, 'mdt_1');
    $this->service->handlePaymentWebhook('tr_first1');
    actingInShop($this->owner, $this->shop->fresh());

    Livewire::test(SubscriptionPage::class)->assertSee('Betalingen')->assertSee('Betaald');
});

it('shows a processing notice when the owner returns before the webhook arrived', function () {
    $this->service->startCheckout($this->shop, $this->owner);
    actingInShop($this->owner, $this->shop->fresh());

    Livewire::withQueryParams(['terug' => 1])->test(SubscriptionPage::class)->assertSee('We verwerken je betaling');
});

it('keeps the subscription screen away from barbers and other shops', function () {
    [$barber] = barberIn($this->shop);
    [, $otherOwner] = shopWithOwner();

    $this->actingAs($barber)->get('/app/abonnement')->assertForbidden();
    $this->actingAs($otherOwner)->get('/app/abonnement')->assertOk()->assertDontSee('Verlopen');
});

it('shows a gift of the admin as free period on the screen', function () {
    [$shop, $owner] = shopInState('free');
    actingInShop($owner, $shop);

    Livewire::test(SubscriptionPage::class)->assertSee('Gratis')->assertSee('Je gebruikt BarberCuts gratis tot');
});
