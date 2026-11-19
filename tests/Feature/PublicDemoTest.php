<?php

use App\Livewire\Onboarding;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\User;
use App\Services\Demo\DemoShopService;
use App\Support\PitcherMessages;
use App\Support\Referral;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function demoPitcher(string $name = 'Sam Jansen'): User
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

it('shows the demo page to anyone, with the pitcher of the link', function () {
    $pitcher = demoPitcher();

    $this->get('/probeer')->assertOk()->assertSee('Start de demo')->assertDontSee('Je kreeg deze link van')->assertHeaderMissing('X-Robots-Tag');

    $this->get('/probeer/'.$pitcher->referral_code)
        ->assertOk()
        ->assertSee('Je kreeg deze link van')
        ->assertSee('Sam')
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertSessionHas(Referral::SESSION, $pitcher->referral_code);
});

it('still shows the page for an unknown code, without remembering it', function () {
    $this->get('/probeer/onbekend')->assertOk()->assertSee('Start de demo')->assertSessionMissing(Referral::SESSION);
});

it('creates nothing on a plain visit, so link previews do not start demos', function () {
    $this->get('/probeer/'.demoPitcher()->referral_code)->assertOk();

    expect(Barbershop::query()->where('is_demo', true)->count())->toBe(0)
        ->and(User::query()->where('is_demo_visitor', true)->count())->toBe(0);
});

it('starts an own filled demo shop without an account and lands in the app', function () {
    $pitcher = demoPitcher();

    $this->get('/probeer/'.$pitcher->referral_code);
    $this->post('/probeer')->assertRedirect(route('app.today'));

    $visitor = User::query()->where('is_demo_visitor', true)->sole();
    $shop = Barbershop::query()->where('is_demo', true)->sole();

    expect(auth()->id())->toBe($visitor->id)
        ->and($visitor->isOwnerOf($shop))->toBeTrue()
        ->and($visitor->hasVerifiedEmail())->toBeTrue()
        ->and($visitor->email)->toEndWith('@'.DemoShopService::VISITOR_EMAIL_DOMAIN)
        ->and($shop->demo_pitcher_id)->toBeNull()
        ->and($shop->demo_referrer_id)->toBe($pitcher->id)
        ->and($pitcher->fresh()->demo_link_starts)->toBe(1)
        ->and(Appointment::withoutGlobalScopes()->where('barbershop_id', $shop->id)->count())->toBeGreaterThan(300)
        ->and(ActivityLog::query()->where('action', 'demo.visitor_started')->count())->toBe(1);

    $this->get('/app')->assertOk()
        ->assertSee('Welkom in je demozaak')
        ->assertSee('Eigen zaak')
        ->assertSee(route('demo.try.restart'), false)
        ->assertDontSee('Bevestig je e-mailadres');
    $this->get('/app/omzet')->assertOk();
    $this->get('/app/agenda')->assertOk();
    $this->get('/app/abonnement')->assertNotFound();
});

it('keeps the running demo when the button is tapped again', function () {
    $this->post('/probeer')->assertRedirect(route('app.today'));
    $this->post('/probeer')->assertRedirect(route('app.today'));

    expect(Barbershop::query()->where('is_demo', true)->count())->toBe(1);
});

it('sends a pitcher to his own demo instead', function () {
    $this->actingAs(demoPitcher())->post('/probeer')->assertRedirect(route('demo'));

    expect(User::query()->where('is_demo_visitor', true)->count())->toBe(0);
});

it('logs out someone with a real account before starting, and says so beforehand', function () {
    [$shop, $owner] = shopWithOwner();

    $this->actingAs($owner)->get('/probeer')->assertSee('Je bent ingelogd als '.$owner->email);
    $this->actingAs($owner)->post('/probeer')->assertRedirect(route('app.today'));

    expect(auth()->user()->is_demo_visitor)->toBeTrue()
        ->and($owner->fresh()->isOwnerOf($shop))->toBeTrue();
});

it('limits how many demos one network can start', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post('/probeer')->assertRedirect(route('app.today'));
        auth()->logout();
    }

    $this->from('/probeer')->post('/probeer')->assertRedirect('/probeer')->assertSessionHas('demo_error');

    expect(Barbershop::query()->where('is_demo', true)->count())->toBe(5);
});

it('restarts the demo with fresh data and keeps the pitcher', function () {
    $pitcher = demoPitcher();
    $this->get('/probeer/'.$pitcher->referral_code);
    $this->post('/probeer');

    $first = Barbershop::query()->where('is_demo', true)->sole();

    $this->post('/probeer/opnieuw')->assertRedirect(route('app.today'));

    $second = Barbershop::query()->where('is_demo', true)->sole();

    expect($second->id)->not->toBe($first->id)
        ->and($second->demo_referrer_id)->toBe($pitcher->id)
        ->and(User::query()->where('is_demo_visitor', true)->count())->toBe(1);

    $this->get('/app')->assertOk();
});

it('does not let a normal account use the visitor restart', function () {
    [, $owner] = shopWithOwner();

    $this->actingAs($owner)->post('/probeer/opnieuw')->assertNotFound();
});

it('ends the demo when the visitor signs up, and credits the pitcher of the link', function () {
    $pitcher = demoPitcher();
    $this->get('/probeer/'.$pitcher->referral_code);
    $this->post('/probeer');

    $visitor = User::query()->where('is_demo_visitor', true)->sole();

    $this->get('/aanmelden')->assertOk()->assertSee('Meld je zaak aan');

    expect(auth()->check())->toBeFalse()
        ->and(User::query()->whereKey($visitor->id)->exists())->toBeFalse()
        ->and(Barbershop::query()->where('is_demo', true)->count())->toBe(0)
        ->and(session(Referral::SESSION))->toBe($pitcher->referral_code);

    Livewire::test(Onboarding::class)
        ->set('name', 'Mo Kapper')->set('email', 'mo@example.com')->set('password', 'geheim-wachtwoord')
        ->call('saveAccount');

    Livewire::actingAs(User::query()->where('email', 'mo@example.com')->sole())->test(Onboarding::class)
        ->set('shopName', 'Mo Cuts')->set('city', 'Utrecht')->set('phone', '0612345678')->set('slug', 'mo-cuts')
        ->call('saveShop');

    expect(Barbershop::query()->where('slug', 'mo-cuts')->sole()->referred_by_user_id)->toBe($pitcher->id);
});

it('wipes the visitor account together with an expired demo', function () {
    $this->post('/probeer');

    $shop = Barbershop::query()->where('is_demo', true)->sole();
    $shop->forceFill(['demo_expires_at' => now()->subMinute()])->save();

    $this->get('/app')->assertRedirect(route('demo.try'));

    expect(app(DemoShopService::class)->deleteExpired())->toBe(1)
        ->and(User::query()->where('is_demo_visitor', true)->count())->toBe(0)
        ->and(Barbershop::query()->whereKey($shop->id)->exists())->toBeFalse();
});

it('lets a visitor with an expired demo start a new one', function () {
    $this->post('/probeer');
    Barbershop::query()->where('is_demo', true)->update(['demo_expires_at' => now()->subMinute()]);

    $this->post('/probeer')->assertRedirect(route('app.today'));
    $this->get('/app')->assertOk();

    expect(Barbershop::query()->where('is_demo', true)->where('demo_expires_at', '>', now())->count())->toBe(1);
});

it('never touches real shops or the demo of a pitcher', function () {
    [$realShop] = shopWithOwner();
    $pitcher = demoPitcher();
    $pitcherDemo = app(DemoShopService::class)->start($pitcher);

    [$visitor] = app(DemoShopService::class)->startForVisitor($pitcher);
    app(DemoShopService::class)->endVisit($visitor);

    expect(Barbershop::query()->whereKey($realShop->id)->exists())->toBeTrue()
        ->and(Barbershop::query()->whereKey($pitcherDemo->id)->exists())->toBeTrue()
        ->and($pitcher->fresh())->not->toBeNull();
});

it('shows the demo link with share buttons to pitchers', function () {
    $pitcher = demoPitcher();
    $pitcher->forceFill(['demo_link_starts' => 7])->save();

    $this->actingAs($pitcher)->get('/pitcher')->assertOk()->assertSee('Jouw demolink')->assertSee('probeer/'.$pitcher->referral_code)->assertSee('7× gestart');
    $this->actingAs($pitcher)->get('/demo')->assertOk()->assertSee('Jouw demolink');

    $keys = array_column(PitcherMessages::for($pitcher), 'key');
    $demoMessage = collect(PitcherMessages::for($pitcher))->firstWhere('key', 'demo_link');

    expect($keys)->toContain('demo_link', 'demo_link_short', 'after_demo')
        ->and($demoMessage['text'])->toContain($pitcher->demoLink());
});

it('reserves the probeer slug', function () {
    expect(config('barbercuts.reserved_slugs'))->toContain('probeer');
});

it('offers the pitcher dashboard in the account switcher, only to pitchers', function () {
    $pitcher = demoPitcher();

    $this->actingAs($pitcher)->get('/pitcher')->assertOk()->assertSee('Pitcherdashboard')->assertSee(route('pitcher.overview'), false);
    $this->actingAs($pitcher)->get('/demo')->assertOk()->assertSee('Pitcherdashboard')->assertSee('Wissel van account');

    [, $owner] = shopWithOwner();
    $this->actingAs($owner)->get('/app/meer')->assertOk()->assertSee('Wissel van account')->assertDontSee('Pitcherdashboard');
});

it('busts cached icons with a version in the url', function () {
    $this->get('/')->assertSee('/icons/apple-touch-icon.png?v=', false)->assertSee('/manifest.webmanifest?v=', false);

    expect((string) file_get_contents(public_path('sw.js')))->toContain('barbercuts-static-v4');
});
