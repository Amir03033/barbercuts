<?php

namespace App\Livewire\App;

use App\Enums\AccessState;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BookingException;
use App\Livewire\Concerns\InShop;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription as SubscriptionModel;
use App\Services\Billing\SubscriptionService;
use App\Support\Features;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Abonnement (§9.1): status, prijs, starten en opzeggen. Alleen voor de eigenaar, en alleen als facturering aan staat (B12).
 */
#[Layout('layouts::app', ['active' => 'more'])]
#[Title('Abonnement')]
class Subscription extends Component
{
    use InShop;

    #[Url(as: 'terug')]
    public bool $returned = false;

    public function mount(): void
    {
        $this->authorizeOwner();

        // Gratis start: geen betaalschermen.
        abort_unless(Features::billing(), 404);
    }

    public function start(SubscriptionService $subscriptions): void
    {
        $this->authorizeOwner();

        try {
            $url = $subscriptions->startCheckout($this->shop(), $this->user());
        } catch (BookingException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatch('toast', message: 'Betalen starten is niet gelukt. Probeer het later opnieuw.', type: 'error');

            return;
        }

        $this->redirect($url);
    }

    public function cancel(SubscriptionService $subscriptions): void
    {
        $this->authorizeOwner();

        $subscription = $this->shop()->activeSubscription();

        if ($subscription === null || $subscription->status !== SubscriptionStatus::Active) {
            return;
        }

        try {
            $subscriptions->cancel($subscription);
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatch('toast', message: 'Opzeggen is niet gelukt. Probeer het later opnieuw.', type: 'error');

            return;
        }

        $this->dispatch('close-sheet');
        $this->dispatch('toast', message: 'Je abonnement is opgezegd');
    }

    public function render(): View
    {
        $shop = $this->shop();
        $state = $shop->accessState();
        $subscription = $shop->subscriptions()->latest('id')->first();
        $plan = $shop->plan ?? Plan::current();

        $payments = Payment::query()
            ->where('barbershop_id', $shop->id)
            ->where('payable_type', SubscriptionModel::class)
            ->latest('id')
            ->limit(10)
            ->get();

        return view('livewire.app.subscription', [
            'shop' => $shop,
            'state' => $state,
            'subscription' => $subscription,
            'running' => $shop->activeSubscription(),
            'plan' => $plan,
            'payments' => $payments,
            'processing' => $this->returned && $payments->first()?->status === 'open',
            'canStart' => $state !== AccessState::Suspended && $shop->activeSubscription() === null,
        ]);
    }
}
