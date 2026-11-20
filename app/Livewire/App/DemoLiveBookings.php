<?php

namespace App\Livewire\App;

use App\Livewire\Concerns\InShop;
use App\Services\Demo\DemoShopService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Live boekingen in een demozaak: na anderhalve minuut, en daarna af en toe, komt er een online boeking binnen met een
 * melding bovenin, zodat de agenda voelt als een echte drukke dag. Staat alleen in demozaken (in de layout van /app).
 */
class DemoLiveBookings extends Component
{
    use InShop;

    /** Zo lang na de start van de demo komt de eerste boeking binnen (seconden). */
    public const int FIRST_AFTER = 90;

    /** Zo lang tussen twee boekingen (seconden). */
    public const int BETWEEN = 150;

    /** Hooguit zoveel boekingen per demo. */
    public const int MAX = 4;

    public function tick(DemoShopService $demos): void
    {
        $shop = $this->shop();

        if (! $shop->is_demo || $shop->demo_live_bookings >= self::MAX) {
            return;
        }

        $last = $shop->demo_live_booking_at ?? $shop->created_at;
        $wait = $shop->demo_live_booking_at === null ? self::FIRST_AFTER : self::BETWEEN;

        if ($last !== null && $last->diffInSeconds(now()) < $wait) {
            return;
        }

        $appointment = $demos->simulateOnlineBooking($shop);

        if ($appointment === null) {
            return;
        }

        $this->dispatch('demo-booking',
            customer: $appointment->customer->name,
            service: $appointment->service_name,
            when: ucfirst($appointment->whenLabel()),
            staff: $appointment->staffMember->name,
            url: route('app.calendar', ['dag' => $appointment->localStart()->toDateString(), 'kapper' => $appointment->staff_member_id]),
        );

        // Overzicht en agenda tonen de nieuwe afspraak meteen.
        $this->dispatch('demo-booking-created');
    }

    public function render(): View
    {
        return view('livewire.app.demo-live-bookings', [
            'active' => $this->shop()->is_demo && $this->shop()->demo_live_bookings < self::MAX,
        ]);
    }
}
