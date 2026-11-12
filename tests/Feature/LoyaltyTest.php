<?php

use App\Exceptions\BookingException;
use App\Livewire\App\Loyalty;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\LoyaltyCard;
use App\Models\Service;
use App\Services\Booking\AppointmentService;
use Livewire\Livewire;

/**
 * Een begonnen, geplande afspraak (klaar om te voltooien).
 */
function startedVisit(Barbershop $shop, array $attributes = []): Appointment
{
    return Appointment::factory()->create([
        'barbershop_id' => $shop->id, 'starts_at' => now()->subHour(), 'ends_at' => now()->subMinutes(30), 'blocked_until' => now()->subMinutes(30), ...$attributes,
    ]);
}

test('de eigenaar richt de spaarkaart in; een stempel per voltooide afspraak, verzilveren bij een volle kaart, en terugdraaien kan', function () {
    [$shop, $owner] = shopWithOwner();
    [$barber] = barberIn($shop);
    $counts = Service::factory()->create(['barbershop_id' => $shop->id]);
    $other = Service::factory()->create(['barbershop_id' => $shop->id]);

    actingInShop($barber, $shop);
    Livewire::test(Loyalty::class)->assertForbidden();

    actingInShop($owner, $shop);
    Livewire::test(Loyalty::class)
        ->set('active', true)->set('stamps', '5')->set('rewardType', 'discount')->set('rewardAmount', '')
        ->call('save')->assertHasErrors('rewardAmount')
        ->set('rewardAmount', '10,00')->set('serviceIds', [(string) $counts->id])
        ->call('save')->assertHasNoErrors();

    $card = inShop($shop, fn () => LoyaltyCard::query()->sole());
    expect($card->is_active)->toBeTrue()->and($card->amountAfterReward(2500))->toBe(1500);

    $appointments = app(AppointmentService::class);
    $first = startedVisit($shop, ['service_id' => $counts->id]);
    $customer = $first->customer;

    // Een stempel bij de dienst die meetelt, niet bij de andere.
    $appointments->complete($first, $owner, 2500);
    $appointments->complete(startedVisit($shop, ['service_id' => $other->id, 'customer_id' => $customer->id]), $owner, 2500);
    expect($customer->fresh()->loyalty_stamps)->toBe(1);

    // Verzilveren kan pas bij een volle kaart, haalt de stempels eraf en levert zelf geen stempel op.
    $reward = startedVisit($shop, ['service_id' => $counts->id, 'customer_id' => $customer->id]);
    expect(fn () => $appointments->complete($reward, $owner, 1500, redeemReward: true))->toThrow(BookingException::class);

    $customer->forceFill(['loyalty_stamps' => 6])->save();
    $appointments->complete($reward->fresh(), $owner, 1500, redeemReward: true);
    expect($customer->fresh()->loyalty_stamps)->toBe(1)->and($reward->fresh()->loyalty_redeemed_stamps)->toBe(5);

    // Ongedaan maken zet alles terug.
    $appointments->undoComplete($reward->fresh(), $owner);
    $appointments->undoComplete($first->fresh(), $owner);
    expect($customer->fresh()->loyalty_stamps)->toBe(5);

    // De klant ziet de kaart bij de afspraak en op de pagina van de zaak.
    $this->get('/afspraak/'.$first->manage_token)->assertOk()->assertSee('5 / 5')->assertSee('Je kaart is vol');
    $this->get('/'.$shop->slug)->assertOk()->assertSee('spaar 5 stempels');
});
