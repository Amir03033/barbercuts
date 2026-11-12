<?php

use App\Enums\WaitlistStatus;
use App\Exceptions\BookingException;
use App\Livewire\App\Winback;
use App\Livewire\Shop\Book;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\TimeOff;
use App\Models\WaitlistEntry;
use App\Notifications\CustomerNudge;
use App\Services\Booking\CustomerInput;
use App\Services\WaitlistService;
use App\Services\WinbackService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

test('terughalen: alleen klanten met toestemming krijgen één bericht, en het resultaat telt de terugkomers', function () {
    Notification::fake();
    [$shop, $owner] = shopWithOwner();
    inShop($shop, fn () => $shop->settings()->first()->update(['winback_weeks' => 6]));

    $customer = fn (array $state) => Customer::factory()->create(['barbershop_id' => $shop->id, 'email' => fake()->safeEmail(), 'last_visit_at' => now()->subWeeks(8), ...$state]);
    $lost = $customer(['marketing_opt_in' => true]);
    $customer(['marketing_opt_in' => false]);
    $customer(['marketing_opt_in' => true, 'last_visit_at' => now()->subWeeks(2)]);

    $winback = app(WinbackService::class);

    expect($winback->sendFor($shop))->toBe(1)->and($winback->sendFor($shop))->toBe(0);
    Notification::assertSentOnDemandTimes(CustomerNudge::class, 1);

    // De klant boekt daarna en komt: dat telt als teruggehaald, met de omzet.
    Appointment::factory()->create(['barbershop_id' => $shop->id, 'customer_id' => $lost->id, 'status' => 'completed', 'amount_paid_cents' => 2500]);
    expect($winback->results($shop))->toBe(['sent' => 1, 'returned' => 1, 'revenue_cents' => 2500]);

    // Afmelden via de link in het bericht.
    $this->get(URL::signedRoute('marketing.unsubscribe', ['customer' => $lost->id]))->assertOk()->assertSee('afgemeld');
    $this->get('/afmelden/'.$lost->id)->assertForbidden();
    expect($lost->fresh()->marketing_opt_in)->toBeFalse();

    // De instellingen zijn alleen voor de eigenaar.
    [$barber] = barberIn($shop);
    actingInShop($barber, $shop);
    Livewire::test(Winback::class)->assertForbidden();
    actingInShop($owner, $shop);
    Livewire::test(Winback::class)->set('enabled', false)->call('save')->assertHasNoErrors();
    expect(inShop($shop, fn () => $shop->settings()->first()->winback_weeks))->toBeNull();
});

test('wachtlijst: de eerste krijgt een kwartier voorrang, daarna de volgende, en boeken sluit de inschrijving af', function () {
    Notification::fake();
    [$shop] = shopWithOwner();
    staffWithHours($shop);
    $service = Service::factory()->create(['barbershop_id' => $shop->id]);
    // Over twee dagen, maar niet op zondag: dan werkt de kapper niet.
    $day = now($shop->timezone)->addDays(2);
    $date = ($day->isSunday() ? $day->addDay() : $day)->toDateString();
    $waitlist = app(WaitlistService::class);

    $join = fn (string $phone) => $waitlist->join($shop, $service, null, $date, new CustomerInput('Klant '.$phone, $phone, "k{$phone}@example.com"));
    $first = $join('+31611111111');
    $second = $join('+31622222222');

    // Dubbel aanmelden geeft dezelfde inschrijving; zonder bereikbaar adres kan het niet.
    expect($join('+31611111111')->id)->toBe($first->id);
    expect(fn () => $waitlist->join($shop, $service, null, $date, new CustomerInput('Zonder mail', '+31633333333')))->toThrow(BookingException::class);

    // Er is plek: alleen de eerste krijgt een aanbod.
    expect($waitlist->advanceAll())->toBe(1)->and($waitlist->advanceAll())->toBe(0);
    expect($first->fresh()->status)->toBe(WaitlistStatus::Offered)->and($second->fresh()->status)->toBe(WaitlistStatus::Waiting);
    Notification::assertSentOnDemandTimes(CustomerNudge::class, 1);
    $this->get($first->fresh()->url())->assertOk()->assertSee('Er is een plek vrij');

    // Voorrang verlopen: de eerste heeft zijn kans gehad, de tweede is aan de beurt en boekt.
    WaitlistEntry::withoutGlobalScopes()->whereKey($first->id)->update(['offer_expires_at' => now()->subMinute()]);
    $waitlist->advanceAll();
    expect($first->fresh()->status)->toBe(WaitlistStatus::Missed);

    $entry = WaitlistEntry::withoutGlobalScopes()->findOrFail($second->id);
    $appointment = $waitlist->book($entry, $waitlist->slots($entry)[0]);

    expect($entry->fresh()->status)->toBe(WaitlistStatus::Booked)
        ->and($appointment->customer_id)->toBe($second->customer_id)
        ->and(fn () => $waitlist->book($first->fresh(), $waitlist->slots($entry)[0]))->toThrow(BookingException::class);
});

test('boekflow: bij een volle dag kan de klant zich op de wachtlijst zetten', function () {
    [$shop] = shopWithOwner();
    $staff = staffWithHours($shop);
    $service = Service::factory()->create(['barbershop_id' => $shop->id]);
    $day = now($shop->timezone)->addDays(2);
    $day = $day->isSunday() ? $day->addDay() : $day;

    // De kapper is die dag vrij: geen tijden, maar de zaak is niet dicht.
    TimeOff::factory()->create(['barbershop_id' => $shop->id, 'staff_member_id' => $staff->id, 'starts_at' => $day->copy()->startOfDay()->utc(), 'ends_at' => $day->copy()->endOfDay()->utc()]);

    inShop($shop, fn () => Livewire::test(Book::class)
        ->call('chooseService', $service->id)
        ->assertSee('Staat je dag er niet bij?')
        ->call('startWaitlist')->assertSet('step', 'waitlist')
        ->set('waitlistDate', $day->toDateString())
        ->set('name', 'Wachtende Klant')->set('phone', '0612345678')->set('email', 'wacht@example.com')
        ->call('joinWaitlist')->assertHasNoErrors()->assertRedirect());

    expect(WaitlistEntry::withoutGlobalScopes()->where('barbershop_id', $shop->id)->whereDate('date', $day->toDateString())->count())->toBe(1);
});
