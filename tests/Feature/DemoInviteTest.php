<?php

use App\Enums\AppointmentSource;
use App\Livewire\App\DemoLiveBookings;
use App\Livewire\Onboarding;
use App\Livewire\PitcherOverview;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\DemoInvite;
use App\Models\DemoVisit;
use App\Models\User;
use App\Notifications\DemoStarted;
use App\Services\Demo\DemoShopService;
use App\Support\DemoCarryover;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function invitingPitcher(string $name = 'Sam Jansen'): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->forceFill(['is_pitcher' => true])->save();
    $user->referralCode();

    return $user->fresh();
}

beforeEach(function () {
    RateLimiter::clear('demo-visitor:all');
    RateLimiter::clear('demo-visitor:ip:127.0.0.1');
});

// ── 1. Persoonlijke demo ─────────────────────────────────────────────────────────────────────────────────

it('lets a pitcher set up a demo with the name of a shop', function () {
    $pitcher = invitingPitcher();

    Livewire::actingAs($pitcher)->test(PitcherOverview::class)
        ->set('inviteShopName', ' Fadez Barbershop ')->set('inviteCity', 'Zwolle')->set('inviteColor', '#dc2626')
        ->call('createInvite')
        ->assertHasNoErrors()
        ->assertSee('Fadez Barbershop')
        ->assertSee('probeer/'.$pitcher->referral_code.'/', false);

    $invite = DemoInvite::query()->sole();

    expect($invite->user_id)->toBe($pitcher->id)
        ->and($invite->shop_name)->toBe('Fadez Barbershop')
        ->and($invite->city)->toBe('Zwolle')
        ->and($invite->accent_color)->toBe('#dc2626')
        ->and($invite->token)->toMatch('/^[a-z0-9]{8}$/');
});

it('validates the invite and only offers the preset colors', function () {
    Livewire::actingAs(invitingPitcher())->test(PitcherOverview::class)
        ->set('inviteShopName', '')->call('createInvite')->assertHasErrors('inviteShopName')
        ->set('inviteShopName', 'Fadez')->set('inviteColor', '#123456')->call('createInvite')->assertHasErrors('inviteColor');

    expect(DemoInvite::query()->count())->toBe(0);
});

it('only lets a pitcher delete his own invites', function () {
    $other = DemoInvite::factory()->create();

    Livewire::actingAs(invitingPitcher())->test(PitcherOverview::class)->call('deleteInvite', $other->id);

    expect($other->fresh())->not->toBeNull();
});

it('shows the personal demo page with the name of the shop', function () {
    $invite = DemoInvite::factory()->create(['shop_name' => 'Fadez Barbershop']);
    $code = $invite->pitcher->referralCode();

    $this->get('/probeer/'.$code.'/'.$invite->token)->assertOk()
        ->assertSee('Zo ziet')->assertSee('Fadez Barbershop')->assertSee('Bekijk je zaak')
        ->assertSee('name="invite" value="'.$invite->token.'"', false)
        ->assertSee('/probeer/'.$code.'/'.$invite->token.'/deel.png', false);

    // Een token van een andere pitcher werkt niet met deze code
    $otherCode = invitingPitcher('Mo Bakker')->referralCode();
    $this->get('/probeer/'.$otherCode.'/'.$invite->token)->assertOk()->assertDontSee('Fadez Barbershop');
});

it('builds the demo with the name, city and color of the invite and tells the pitcher', function () {
    Notification::fake();
    $invite = DemoInvite::factory()->create(['shop_name' => 'Fadez Barbershop', 'city' => 'Zwolle', 'accent_color' => '#dc2626']);

    $this->post('/probeer', ['code' => $invite->pitcher->referralCode(), 'invite' => $invite->token])->assertRedirect(route('app.today'));

    $shop = Barbershop::query()->where('is_demo', true)->sole();

    expect($shop->name)->toBe('Fadez Barbershop')
        ->and($shop->city)->toBe('Zwolle')
        ->and($shop->accent_color)->toBe('#dc2626')
        ->and($shop->description)->toContain('Zwolle')
        ->and($shop->demo_referrer_id)->toBe($invite->user_id)
        ->and($invite->fresh()->starts)->toBe(1)
        ->and(DemoVisit::query()->sole()->demo_invite_id)->toBe($invite->id)
        ->and(DB::table('reviews')->where('barbershop_id', $shop->id)->where('comment', 'like', '%Utrecht%')->exists())->toBeFalse();

    Notification::assertSentTo($invite->pitcher, DemoStarted::class, function (DemoStarted $notification, array $channels) use ($invite): bool {
        $mail = $notification->toMail($invite->pitcher);

        return $channels === ['mail'] && str_contains($mail->subject, 'Fadez Barbershop');
    });

    $this->get('/app')->assertOk()->assertSee('Fadez Barbershop');
});

it('keeps the personal name when the visitor restarts the demo', function () {
    $invite = DemoInvite::factory()->create(['shop_name' => 'Fadez Barbershop']);
    $this->post('/probeer', ['code' => $invite->pitcher->referralCode(), 'invite' => $invite->token]);

    $this->post('/probeer/opnieuw')->assertRedirect(route('app.today'));

    expect(Barbershop::query()->where('is_demo', true)->sole()->name)->toBe('Fadez Barbershop');
});

// ── 4. Melding voor de pitcher ───────────────────────────────────────────────────────────────────────────

it('notifies the pitcher of a plain demo link, but nobody without a pitcher', function () {
    Notification::fake();
    $pitcher = invitingPitcher();

    $this->post('/probeer')->assertRedirect(route('app.today'));
    Notification::assertNothingSent();

    auth()->logout();
    $this->get('/probeer/'.$pitcher->referral_code);
    $this->post('/probeer')->assertRedirect(route('app.today'));

    Notification::assertSentTo($pitcher, DemoStarted::class, fn (DemoStarted $notification): bool => str_contains($notification->toMail($pitcher)->subject, 'Iemand bekijkt je demo'));
});

it('shows recent demos in the pitcher overview, marked when the shop signed up', function () {
    $invite = DemoInvite::factory()->create(['shop_name' => 'Fadez Barbershop']);
    $pitcher = $invite->pitcher;
    [$shop] = shopWithOwner();

    $visit = new DemoVisit(['pitcher_id' => $pitcher->id, 'demo_invite_id' => $invite->id]);
    $visit->signed_up_barbershop_id = $shop->id;
    $visit->save();

    Livewire::actingAs($pitcher)->test(PitcherOverview::class)->assertSee("Recente demo's", false)->assertSee('Fadez Barbershop')->assertSee('Aangemeld');
});

// ── 6. Deelafbeelding ────────────────────────────────────────────────────────────────────────────────────

it('serves a share image for the plain, pitcher and personal demo link', function () {
    $invite = DemoInvite::factory()->create(['shop_name' => 'Fadez Barbershop', 'accent_color' => '#dc2626']);
    $code = $invite->pitcher->referralCode();

    foreach (['/probeer/deel.png', '/probeer/'.$code.'/deel.png', '/probeer/'.$code.'/'.$invite->token.'/deel.png'] as $url) {
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        [$width, $height] = getimagesize($response->baseResponse->getFile()->getPathname());
        expect([$width, $height])->toBe([1200, 630]);
    }

    $this->get('/probeer/'.$code)->assertSee('/probeer/'.$code.'/deel.png', false)->assertSee('nodigt je uit', false);
});

// ── 2. Demo wordt je echte zaak ──────────────────────────────────────────────────────────────────────────

it('carries the demo settings over to the sign-up, without the fake customers', function () {
    $invite = DemoInvite::factory()->create(['shop_name' => 'Fadez Barbershop', 'city' => 'Zwolle', 'accent_color' => '#dc2626']);
    $this->post('/probeer', ['code' => $invite->pitcher->referralCode(), 'invite' => $invite->token]);

    // De bezoeker past in de demo een prijs aan
    $demo = Barbershop::query()->where('is_demo', true)->sole();
    DB::table('services')->where('barbershop_id', $demo->id)->where('name', 'Knippen')->update(['price_cents' => 2900]);

    $this->get('/aanmelden')->assertOk()->assertSee('Je instellingen uit de demo gaan mee');

    $carryover = session(DemoCarryover::SESSION);
    expect($carryover['shop_name'])->toBe('Fadez Barbershop')->and($carryover['accent'])->toBe('#dc2626');

    Livewire::test(Onboarding::class)->assertSet('shopName', 'Fadez Barbershop')->assertSet('city', 'Zwolle')
        ->set('name', 'Mo Kapper')->set('email', 'mo@example.com')->set('password', 'geheim-wachtwoord')
        ->call('saveAccount');

    $owner = User::query()->where('email', 'mo@example.com')->sole();

    $component = Livewire::actingAs($owner)->test(Onboarding::class)
        ->assertSet('step', 2)->assertSet('shopName', 'Fadez Barbershop')->assertSet('slug', fn (string $slug) => str_starts_with($slug, 'fadez'))
        ->set('phone', '0612345678')->call('saveShop')->assertHasNoErrors()
        ->set('mode', 'solo')->call('saveBarbers')
        ->call('saveHours');

    expect(collect($component->get('services'))->firstWhere('name', 'Knippen')['price'])->toBe('29,00');

    $component->call('saveServices')->assertSet('step', 6);

    $shop = Barbershop::query()->where('is_demo', false)->where('name', 'Fadez Barbershop')->sole();

    expect($shop->accent_color)->toBe('#dc2626')
        ->and($shop->referred_by_user_id)->toBe($invite->user_id)
        ->and(DB::table('services')->where('barbershop_id', $shop->id)->where('name', 'Knippen')->value('price_cents'))->toBe(2900)
        ->and(DB::table('customers')->where('barbershop_id', $shop->id)->count())->toBe(0)
        ->and(DemoVisit::query()->sole()->signed_up_barbershop_id)->toBe($shop->id)
        ->and(session()->has(DemoCarryover::SESSION))->toBeFalse();
});

it('does not carry over the default demo name', function () {
    $this->post('/probeer');
    $this->get('/aanmelden')->assertOk();

    expect(session(DemoCarryover::SESSION)['shop_name'])->toBeNull()
        ->and(session(DemoCarryover::SESSION)['services'])->not->toBeEmpty();

    Livewire::test(Onboarding::class)->assertSet('shopName', '');
});

// ── 8. Live boekingen ────────────────────────────────────────────────────────────────────────────────────

it('drops in a live online booking after a while, a few times at most', function () {
    $this->travelTo(at('2026-11-18 10:00'));
    $pitcher = invitingPitcher();
    $shop = app(DemoShopService::class)->start($pitcher);
    actingInShop($pitcher, $shop);

    $count = fn (): int => Appointment::withoutGlobalScopes()->where('barbershop_id', $shop->id)->count();
    $before = $count();

    Livewire::test(DemoLiveBookings::class)->call('tick')->assertNotDispatched('demo-booking');
    expect($count())->toBe($before);

    $this->travel(DemoLiveBookings::FIRST_AFTER + 1)->seconds();

    Livewire::test(DemoLiveBookings::class)->call('tick')->assertDispatched('demo-booking')->assertDispatched('demo-booking-created');

    $booking = Appointment::withoutGlobalScopes()->where('barbershop_id', $shop->id)->latest('id')->first();
    expect($count())->toBe($before + 1)
        ->and($booking->source)->toBe(AppointmentSource::Online)
        ->and($booking->starts_at->isFuture())->toBeTrue();

    // Te snel achter elkaar: niets
    Livewire::test(DemoLiveBookings::class)->call('tick')->assertNotDispatched('demo-booking');

    foreach (range(2, DemoLiveBookings::MAX + 2) as $round) {
        $this->travel(DemoLiveBookings::BETWEEN + 1)->seconds();
        Livewire::test(DemoLiveBookings::class)->call('tick');
    }

    expect($count())->toBe($before + DemoLiveBookings::MAX);
});

it('never simulates bookings in a real shop', function () {
    [$shop, $owner] = shopWithOwner();
    actingInShop($owner, $shop);

    $this->travel(1)->hours();

    Livewire::test(DemoLiveBookings::class)->call('tick')->assertNotDispatched('demo-booking');
    expect(fn () => app(DemoShopService::class)->simulateOnlineBooking($shop))->toThrow(HttpException::class);
});

// ── Voortgang van persoonlijke demo's voor de pitcher ────────────────────────────────────────────────────

it('counts opening the personal link, but not link previews or the pitcher himself', function () {
    $invite = DemoInvite::factory()->create();
    $url = '/probeer/'.$invite->pitcher->referralCode().'/'.$invite->token;

    $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1')->get($url)->assertOk();
    $this->withHeader('User-Agent', 'WhatsApp/2.24.1 A')->get($url)->assertOk();
    $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get($url)->assertOk();
    $this->actingAs($invite->pitcher)->withHeader('User-Agent', 'Mozilla/5.0 Safari')->get($url)->assertOk();

    expect($invite->fresh()->views)->toBe(1)->and($invite->fresh()->last_viewed_at)->not->toBeNull();
});

it('keeps track of what the visitor looks at in the demo', function () {
    $invite = DemoInvite::factory()->create(['shop_name' => 'Fadez Barbershop']);
    $this->post('/probeer', ['code' => $invite->pitcher->referralCode(), 'invite' => $invite->token]);
    $shop = Barbershop::query()->where('is_demo', true)->sole();

    $this->travel(5)->minutes();
    $this->get('/app')->assertOk();
    $this->get('/app/agenda')->assertOk();
    $this->get('/app/omzet')->assertOk();
    $this->get('/app/omzet')->assertOk();
    $this->get('/'.$shop->slug)->assertOk();

    $visit = DemoVisit::query()->sole();

    expect($visit->page_views)->toBe(5)
        ->and($visit->explored)->toBe(['Overzicht', 'Agenda', 'Omzet', 'Klantpagina'])
        ->and($visit->minutesSpent())->toBe(5);

    // Zelf een afspraak maken telt mee
    Appointment::factory()->for($shop)->create(['created_by_user_id' => auth()->id()]);
    expect($visit->fresh()->appointments_made)->toBe(1);

    // Naar aanmelden: het begin van het aanmelden is zichtbaar
    $this->get('/aanmelden')->assertOk();
    expect($visit->fresh()->signup_started_at)->not->toBeNull();
});

it('never tracks real accounts', function () {
    [$shop, $owner] = shopWithOwner();
    DemoVisit::query()->create(['visitor_id' => null]);

    $this->actingAs($owner)->get('/app')->assertOk();
    $this->actingAs($owner)->get('/app/agenda')->assertOk();

    expect(DemoVisit::query()->sole()->page_views)->toBe(0);
});

it('shows the progress of each personal demo to the pitcher', function () {
    $invite = DemoInvite::factory()->create(['shop_name' => 'Fadez Barbershop']);
    $invite->forceFill(['views' => 3, 'starts' => 1, 'last_started_at' => now()])->save();

    $visit = new DemoVisit(['pitcher_id' => $invite->user_id, 'demo_invite_id' => $invite->id]);
    $visit->forceFill(['page_views' => 12, 'explored' => ['Overzicht', 'Agenda', 'Nieuwe afspraak', 'Omzet'], 'appointments_made' => 2])->save();
    $visit->forceFill(['created_at' => now()->subMinutes(14), 'last_active_at' => now()])->save();

    $stats = PitcherOverview::inviteStats($invite->fresh()->load('visits'));

    expect($stats)->toMatchArray(['views' => 3, 'starts' => 1, 'minutes' => 14, 'appointments' => 2, 'key_explored' => 3, 'step' => 3, 'signed_up' => false])
        ->and($stats['explored'])->toBe(['Overzicht', 'Agenda', 'Nieuwe afspraak', 'Omzet']);

    Livewire::actingAs($invite->pitcher)->test(PitcherOverview::class)
        ->assertSee('Bekeken')->assertSee('14 min')->assertSee('Een mooi moment om te bellen')->assertSee('Nieuwe afspraak')
        ->assertSee("Al je demo's, laatste 30 dagen", false);
});

it('moves the progress on when the shop starts and finishes signing up', function () {
    $invite = DemoInvite::factory()->create();
    $visit = new DemoVisit(['pitcher_id' => $invite->user_id, 'demo_invite_id' => $invite->id]);
    $visit->forceFill(['signup_started_at' => now()])->save();

    expect(PitcherOverview::inviteStats($invite->fresh()->load('visits'))['step'])->toBe(4);

    [$shop] = shopWithOwner();
    $visit->forceFill(['signed_up_barbershop_id' => $shop->id])->save();

    expect(PitcherOverview::inviteStats($invite->fresh()->load('visits'))['step'])->toBe(5);
});
