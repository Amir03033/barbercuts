<?php

use App\Enums\AppointmentSource;
use App\Filament\Resources\Pitchers\PitcherResource;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\User;
use App\Notifications\PitcherInvitation;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingData;
use App\Services\Booking\CustomerInput;
use App\Services\Demo\DemoShopService;
use App\Services\PlatformStatsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function pitcher(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_pitcher' => true])->save();

    return $user;
}

test('de demo bestaat alleen voor pitchers', function () {
    $this->get('/demo')->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get('/demo')->assertNotFound();
    $this->post('/demo/start')->assertNotFound();

    $this->actingAs(pitcher())->get('/demo')->assertOk()->assertSee('Start demo')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('een pitcher start een gevulde demozaak en komt in de app', function () {
    $pitcher = pitcher();

    $this->actingAs($pitcher)->post('/demo/start')->assertRedirect(route('app.today'));

    $shop = Barbershop::query()->where('demo_pitcher_id', $pitcher->id)->sole();

    expect($shop->is_demo)->toBeTrue()
        ->and($shop->demo_expires_at->isFuture())->toBeTrue()
        ->and($pitcher->fresh()->isOwnerOf($shop))->toBeTrue()
        ->and($pitcher->fresh()->demo_count)->toBe(1)
        ->and(Appointment::withoutGlobalScopes()->where('barbershop_id', $shop->id)->count())->toBeGreaterThan(300)
        ->and(Appointment::withoutGlobalScopes()->where('barbershop_id', $shop->id)->where('status', 'completed')->sum('amount_paid_cents'))->toBeGreaterThan(0);

    $this->get('/app')->assertOk()->assertSee('Opnieuw beginnen');
    $this->get('/app/omzet')->assertOk();
    $this->get('/app/agenda')->assertOk();
    $this->get('/'.$shop->slug)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('opnieuw beginnen wist de vorige demo en raakt andere zaken niet', function () {
    [$realShop] = shopWithOwner();
    $pitcher = pitcher();
    $demos = app(DemoShopService::class);

    $first = $demos->start($pitcher);
    $second = $demos->start($pitcher);

    expect(Barbershop::query()->whereKey($first->id)->exists())->toBeFalse()
        ->and(Barbershop::query()->whereKey($second->id)->exists())->toBeTrue()
        ->and(Barbershop::query()->whereKey($realShop->id)->exists())->toBeTrue()
        ->and(Barbershop::query()->where('is_demo', true)->count())->toBe(1);
});

test('verlopen demozaken worden opgeruimd en zijn meteen onbereikbaar', function () {
    [$realShop] = shopWithOwner();
    $pitcher = pitcher();
    $shop = app(DemoShopService::class)->start($pitcher);
    $shop->forceFill(['demo_expires_at' => now()->subMinute()])->save();

    $this->get('/'.$shop->slug)->assertNotFound();
    $this->actingAs($pitcher)->get('/app')->assertRedirect(route('demo'));

    $this->artisan('demo:cleanup')->assertSuccessful();

    expect(Barbershop::query()->where('is_demo', true)->count())->toBe(0)
        ->and(Appointment::withoutGlobalScopes()->where('barbershop_id', $shop->id)->count())->toBe(0)
        ->and(Barbershop::query()->whereKey($realShop->id)->exists())->toBeTrue();
});

test('een demozaak telt niet mee in de platformstatistiek en verstuurt niets', function () {
    Notification::fake();
    $pitcher = pitcher();
    $shop = app(DemoShopService::class)->start($pitcher);

    expect(Barbershop::query()->real()->count())->toBe(0);

    app(PlatformStatsService::class)->compute(now());
    $stats = app(PlatformStatsService::class);

    expect($stats->latest('shops_total'))->toBe(0)
        ->and($stats->latest('active_barbers'))->toBe(0)
        ->and($stats->latest('appointments_done'))->toBe(0);

    // Een online boeking in de demozaak plant geen berichten in.
    $appointment = inShop($shop, function () use ($shop) {
        $service = $shop->services()->first();
        $slot = collect(range(1, 14))
            ->map(fn (int $days) => app(AvailabilityService::class)->slots($shop, $service, null, now($shop->timezone)->addDays($days)->toDateString()))
            ->first(fn ($slots) => count($slots) > 0);

        return app(AppointmentService::class)->book(new BookingData(
            shop: $shop, service: $service, staff: null, startsAt: collect($slot)->first(),
            customer: new CustomerInput('Test Klant', '+31612345678', 'klant@example.com'),
            source: AppointmentSource::Online, notes: null,
        ), null);
    });

    expect(DB::table('appointment_notifications')->where('appointment_id', $appointment->id)->count())->toBe(0);
    Notification::assertNothingSent();
});

test('de admin nodigt een pitcher uit en trekt de toegang weer in', function () {
    Notification::fake();

    $user = PitcherResource::invite('Piet Pitcher', 'piet@example.com');

    expect($user->is_pitcher)->toBeTrue()->and($user->hasVerifiedEmail())->toBeTrue();
    Notification::assertSentTo($user, PitcherInvitation::class);

    $url = URL::temporarySignedRoute('demo.welcome', now()->addDay(), ['user' => $user->id]);
    $this->get($url)->assertRedirectContains('/wachtwoord-herstellen/');
    $this->get('/demo/welkom/'.$user->id)->assertForbidden();
});
