<?php

use App\Enums\AppointmentStatus;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingException;
use App\Jobs\GeocodeBarbershop;
use App\Livewire\App\Reviews;
use App\Livewire\Directory;
use App\Livewire\ManageAppointment;
use App\Models\Appointment;
use App\Models\AppointmentNotification;
use App\Models\Barbershop;
use App\Models\Review;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use App\Services\Demo\DemoShopService;
use App\Services\Geo\Geocoder;
use App\Services\ReviewService;
use App\Services\ShopDirectory;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Een afspraak in een zaak, standaard voltooid (gisteren).
 */
function pastVisit(Barbershop $shop, AppointmentStatus $status = AppointmentStatus::Completed): Appointment
{
    $start = now()->subDay();

    return Appointment::factory()->create([
        'barbershop_id' => $shop->id,
        'status' => $status,
        'starts_at' => $start,
        'ends_at' => $start->copy()->addMinutes(30),
        'blocked_until' => $start->copy()->addMinutes(30),
    ]);
}

/**
 * Een zaak met een aantal reviews van hetzelfde cijfer.
 */
function ratedShop(int $reviews, int $rating, array $attributes = []): Barbershop
{
    [$shop] = shopWithOwner($attributes);

    foreach (range(1, $reviews) as $i) {
        app(ReviewService::class)->submit(pastVisit($shop), $rating, null);
    }

    return $shop->fresh();
}

test('een klant laat via de afspraaklink één review achter, alleen na een voltooid bezoek', function () {
    [$shop] = shopWithOwner();
    $done = pastVisit($shop);
    $planned = pastVisit($shop, AppointmentStatus::Scheduled);

    Livewire::test(ManageAppointment::class, ['token' => $planned->manage_token])
        ->assertDontSee('Review plaatsen');

    Livewire::test(ManageAppointment::class, ['token' => $done->manage_token])
        ->assertSee('Review plaatsen')
        ->call('submitReview')->assertHasErrors('rating')
        ->set('rating', 5)->set('comment', 'Strakke fade')
        ->call('submitReview')->assertHasNoErrors()
        ->assertSee('Jouw review')->assertDontSee('Review plaatsen');

    expect(Review::withoutGlobalScopes()->where('appointment_id', $done->id)->count())->toBe(1)
        ->and($shop->fresh()->rating_avg)->toBe(5.0)
        ->and($shop->fresh()->rating_count)->toBe(1);

    expect(fn () => app(ReviewService::class)->submit($done, 1, null))->toThrow(BookingException::class);

    $this->get('/'.$shop->slug)->assertOk()->assertSee('Strakke fade')->assertSee('AggregateRating');
});

test('de eigenaar reageert en meldt, alleen de admin verbergt en dan telt de review niet meer mee', function () {
    [$shop, $owner] = shopWithOwner();
    [$other] = shopWithOwner();
    [$barber] = barberIn($shop);
    $review = app(ReviewService::class)->submit(pastVisit($shop), 1, 'Slecht');
    app(ReviewService::class)->submit(pastVisit($shop), 5, null);
    $foreign = app(ReviewService::class)->submit(pastVisit($other), 4, 'Andere zaak');

    actingInShop($owner, $shop);

    Livewire::test(Reviews::class)
        ->assertSee('Slecht')->assertDontSee('Andere zaak')
        ->call('startReply', $review->id)->set('reply', 'Excuses, we lossen het op.')->call('saveReply')
        ->call('startReport', $review->id)->set('reportReason', 'Geen klant van ons')->call('report')
        ->assertHasNoErrors();

    // Een review van een andere zaak bestaat hier niet.
    Livewire::test(Reviews::class)->call('startReply', $foreign->id)->assertNotFound();

    $review = Review::withoutGlobalScopes()->find($review->id);
    expect($review->reply)->toBe('Excuses, we lossen het op.')
        ->and($review->reported_at)->not->toBeNull()
        ->and($shop->fresh()->rating_avg)->toBe(3.0);

    // Een kapper leest mee, maar reageert niet.
    actingInShop($barber, $shop);
    Livewire::test(Reviews::class)->assertOk()->call('startReply', $review->id)->assertForbidden();

    app(ReviewService::class)->setHidden($review, true);

    expect($shop->fresh()->rating_avg)->toBe(5.0)->and($shop->fresh()->rating_count)->toBe(1);
    $this->get('/'.$shop->slug)->assertDontSee('Slecht');
});

test('na het voltooien wordt de klant één keer om een review gevraagd', function () {
    [$shop, $owner] = shopWithOwner();
    actingInShop($owner, $shop);
    $service = app(AppointmentService::class);

    $first = pastVisit($shop, AppointmentStatus::Scheduled);
    $service->complete($first, $owner, 2500, 0, PaymentMethod::Pin);

    $request = AppointmentNotification::query()->where('appointment_id', $first->id)->where('type', NotificationType::ReviewRequest->value)->sole();
    expect($request->send_at->isFuture())->toBeTrue();

    // Dezelfde klant binnen 30 dagen: geen tweede verzoek.
    $second = pastVisit($shop, AppointmentStatus::Scheduled);
    $second->forceFill(['customer_id' => $first->customer_id])->save();
    $service->complete($second, $owner, 2500, 0, PaymentMethod::Pin);

    expect(AppointmentNotification::query()->where('appointment_id', $second->id)->count())->toBe(0);

    // Ongedaan maken laat het verzoek vervallen.
    $service->undoComplete($first->fresh(), $owner);
    expect($request->fresh()->status)->toBe(NotificationStatus::Skipped);

    // Uitgezet door de zaak: geen verzoek.
    inShop($shop, fn () => $shop->settings()->first()->update(['review_request_hours' => null]));
    $third = pastVisit($shop, AppointmentStatus::Scheduled);
    $service->complete($third, $owner, 2500, 0, PaymentMethod::Pin);
    expect(AppointmentNotification::query()->where('appointment_id', $third->id)->count())->toBe(0);
});

test('de ranglijst weegt het aantal reviews mee en toont alleen echte, publieke zaken', function () {
    $many = ratedShop(20, 5, ['name' => 'Veel Reviews', 'city' => 'Utrecht']);
    $one = ratedShop(1, 5, ['name' => 'Een Review', 'city' => 'Utrecht']);
    $hidden = ratedShop(5, 5, ['name' => 'Niet Publiek', 'city' => 'Utrecht', 'is_public' => false]);
    app(DemoShopService::class)->start(User::factory()->create());

    $directory = app(ShopDirectory::class);

    expect($many->rating_score)->toBeGreaterThan($one->rating_score)
        ->and($directory->topRated()->pluck('name')->all())->toBe(['Veel Reviews'])
        ->and($directory->inCity('utrecht')->pluck('name')->all())->toBe(['Veel Reviews', 'Een Review']);

    $this->get('/kappers/utrecht')->assertOk()->assertSee('Kappers in Utrecht')->assertSee('Veel Reviews')
        ->assertDontSee('Niet Publiek')->assertDontSee('Studio Fade')->assertSee('ItemList');
    $this->get('/kappers/nergens')->assertNotFound();
    $this->get('/')->assertOk()->assertSee('Veel Reviews');
});

test('zoeken in de buurt sorteert op score en daarna op afstand, binnen de straal', function () {
    // Utrecht centrum, 1 km verderop en Amsterdam (ver weg).
    $near = ratedShop(4, 4, ['name' => 'Dichtbij', 'latitude' => 52.0907, 'longitude' => 5.1214]);
    $best = ratedShop(10, 5, ['name' => 'Beste', 'latitude' => 52.0990, 'longitude' => 5.1300]);
    ratedShop(10, 5, ['name' => 'Amsterdam', 'latitude' => 52.3676, 'longitude' => 4.9041]);

    $found = app(ShopDirectory::class)->near(52.0907, 5.1214);

    expect($found->pluck('name')->all())->toBe(['Beste', 'Dichtbij'])
        ->and($found->last()->distance_km)->toBeLessThan(0.1);

    Livewire::test(Directory::class)
        ->call('locate', 52.0907, 5.1214)->assertSee('Beste')->assertDontSee('Amsterdam')
        ->call('locate', 40.0, 5.0)->assertHasErrors('query');
});

test('adressen en zoekvragen worden via PDOK omgezet naar coördinaten', function () {
    config(['barbercuts.geocoding.enabled' => true]);
    Http::fake(['api.pdok.nl/*' => Http::response(['response' => ['docs' => [['weergavenaam' => 'Utrecht', 'centroide_ll' => 'POINT(5.1214 52.0907)']]]])]);

    expect(app(Geocoder::class)->find('Utrecht'))->toBe(['lat' => 52.0907, 'lng' => 5.1214, 'label' => 'Utrecht']);

    [$shop] = shopWithOwner(['address' => 'Oudegracht 1', 'city' => 'Utrecht']);
    (new GeocodeBarbershop($shop->id))->handle(app(Geocoder::class));

    expect($shop->fresh()->latitude)->toBe(52.0907)->and($shop->fresh()->city_slug)->toBe('utrecht');

    Livewire::test(Directory::class)->set('query', '3511 AB')->call('search')->assertHasNoErrors()->assertSee($shop->name);
});

test('een demozaak heeft reviews maar vraagt er nooit om', function () {
    $pitcher = User::factory()->create();
    $shop = app(DemoShopService::class)->start($pitcher);

    expect($shop->fresh()->rating_count)->toBeGreaterThan(30);

    actingInShop($pitcher, $shop);
    $appointment = Appointment::query()->where('status', 'scheduled')->where('starts_at', '<', now())->first()
        ?? tap(Appointment::query()->where('status', 'scheduled')->first(), fn ($a) => $a->forceFill(['starts_at' => now()->subHour(), 'ends_at' => now()->subMinutes(30), 'blocked_until' => now()->subMinutes(30)])->save());

    app(AppointmentService::class)->complete($appointment, $pitcher, 2500);

    expect(AppointmentNotification::query()->where('appointment_id', $appointment->id)->count())->toBe(0);
});
