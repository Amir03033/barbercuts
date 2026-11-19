<?php

use App\Filament\Resources\Barbershops\Pages\ListBarbershops;
use App\Filament\Resources\Pitchers\PitcherResource;
use App\Livewire\Onboarding;
use App\Livewire\PitcherOverview;
use App\Models\ActivityLog;
use App\Models\Barbershop;
use App\Models\User;
use App\Services\ShopSetupService;
use App\Support\CurrentShop;
use App\Support\Referral;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
| Pitchers brengen zaken aan: een eigen code, het vastleggen van ?ref=, het koppelen bij het aanmelden en het overzicht.
| "Nu" is dinsdag 17 november 2026, 10:00.
*/

function referralPitcher(string $name = 'Sam Bakker'): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->forceFill(['is_pitcher' => true])->save();
    $user->referralCode();

    return $user->fresh();
}

beforeEach(function () {
    $this->travelTo(at('2026-11-17 10:00'));
});

// ── Codes ────────────────────────────────────────────────────────────────────────────────────────────────

it('gives every pitcher a readable, unique code', function () {
    $first = referralPitcher('Sam Bakker');
    $second = referralPitcher('Sam de Vries');

    expect($first->referral_code)->toBe('sam')->and($second->referral_code)->toBe('sam2')
        ->and($first->referralCode())->toBe('sam');
});

it('creates the code when a pitcher is invited', function () {
    Notification::fake();

    $user = PitcherResource::invite('Mo Kaya', 'mo@example.com');

    expect($user->fresh()->referral_code)->toBe('mo');
});

// ── Vastleggen ───────────────────────────────────────────────────────────────────────────────────────────

it('remembers a valid ?ref= code in the session and a cookie for a month', function () {
    referralPitcher('Sam Bakker');

    $response = $this->get('/aanmelden?ref=sam')->assertOk();

    $response->assertSessionHas(Referral::SESSION, 'sam');
    $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === Referral::COOKIE);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->getTimestamp());
});

it('works on every public page, such as the landing and prices pages', function (string $path) {
    referralPitcher('Sam Bakker');

    $this->get($path.'?ref=sam')->assertSessionHas(Referral::SESSION, 'sam');
})->with(['/', '/prijzen']);

it('ignores unknown codes, codes of non-pitchers and weird input', function (string $code) {
    $notPitcher = User::factory()->create();
    $notPitcher->forceFill(['referral_code' => 'gewoon'])->save();

    $this->get('/aanmelden?ref='.$code)->assertSessionMissing(Referral::SESSION);
})->with(['onbekend', 'gewoon', '../etc', 'a b c', '']);

it('keeps the first pitcher when a second link is opened', function () {
    referralPitcher('Sam Bakker');
    referralPitcher('Mo Kaya');

    $this->get('/aanmelden?ref=sam');
    $this->get('/aanmelden?ref=mo')->assertSessionHas(Referral::SESSION, 'sam');
});

it('drops a code whose pitcher lost access, so another link can take over', function () {
    $sam = referralPitcher('Sam Bakker');
    referralPitcher('Mo Kaya');

    $this->get('/aanmelden?ref=sam');
    $sam->forceFill(['is_pitcher' => false])->save();

    $this->get('/aanmelden?ref=mo')->assertSessionHas(Referral::SESSION, 'mo');
});

// ── Koppelen ─────────────────────────────────────────────────────────────────────────────────────────────

it('links a new shop to the pitcher whose link the owner used', function () {
    $pitcher = referralPitcher('Sam Bakker');
    $owner = User::factory()->create();

    $this->withSession([Referral::SESSION => 'sam'])->actingAs($owner);

    Livewire::test(Onboarding::class)
        ->set('shopName', 'JF Cuts')->set('city', 'Utrecht')->set('phone', '06 12345678')
        ->call('saveShop')->assertHasNoErrors();

    expect(Barbershop::query()->where('name', 'JF Cuts')->sole()->referred_by_user_id)->toBe($pitcher->id);
});

it('links nothing without a link, and never to the owner himself or to a non-pitcher', function () {
    $owner = User::factory()->create();
    $setup = app(ShopSetupService::class);

    $plain = $setup->createShop($owner, 'Zonder', 'Utrecht', '+31612345678', 'zonder');
    expect($plain->referred_by_user_id)->toBeNull();

    $self = referralPitcher('Eigen Baas');
    $own = $setup->createShop($self, 'Eigen zaak', 'Utrecht', '+31612345678', 'eigen-zaak', $self);
    expect($own->referred_by_user_id)->toBeNull();

    $normal = User::factory()->create();
    $other = $setup->createShop(User::factory()->create(), 'Andere', 'Utrecht', '+31612345678', 'andere', $normal);
    expect($other->referred_by_user_id)->toBeNull();
});

// ── Overzicht voor de pitcher ───────────────────────────────────────────────────────────────────────────

it('keeps the overview for pitchers only', function () {
    $this->get('/pitcher')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/pitcher')->assertNotFound();
    $this->actingAs(referralPitcher())->get('/pitcher')->assertOk()->assertSee('Mijn aanmeldingen')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('shows the sign-up link, a QR code and an empty state', function () {
    $this->actingAs(referralPitcher('Sam Bakker'));

    Livewire::test(PitcherOverview::class)
        ->assertSee('/aanmelden?ref=sam', false)
        ->assertSee('<svg', false)
        ->assertSee('Nog geen aanmeldingen');
});

it('lists only the shops of this pitcher, with their status', function () {
    billingOn();
    $sam = referralPitcher('Sam Bakker');
    $mo = referralPitcher('Mo Kaya');

    [$trial] = shopInState('trial');
    $trial->forceFill(['name' => 'Proef Cuts', 'referred_by_user_id' => $sam->id, 'phone' => '+31611111111'])->save();
    [$paying] = shopInState('active');
    $paying->forceFill(['name' => 'Betaal Cuts', 'referred_by_user_id' => $sam->id])->save();
    [$foreign] = shopInState('trial');
    $foreign->forceFill(['name' => 'Andermans Cuts', 'referred_by_user_id' => $mo->id])->save();
    [$nobody] = shopInState('trial');
    $nobody->forceFill(['name' => 'Eigen weg Cuts'])->save();

    $this->actingAs($sam);

    Livewire::test(PitcherOverview::class)
        ->assertSee('Proef Cuts')->assertSee('Betaal Cuts')->assertSee('Betaalt')->assertSee('Proef · nog')
        ->assertSee('tel:+31611111111', false)
        ->assertDontSee('Andermans Cuts')->assertDontSee('Eigen weg Cuts');
});

it('points out shops that need a call: trial almost over, trial over or payment failed', function () {
    billingOn();
    $sam = referralPitcher('Sam Bakker');

    [$soon] = shopInState('trial');
    $soon->forceFill(['name' => 'Bijna Cuts', 'referred_by_user_id' => $sam->id, 'trial_ends_at' => now()->addDays(2)])->save();
    [$expired] = shopInState('expired');
    $expired->forceFill(['name' => 'Verlopen Cuts', 'referred_by_user_id' => $sam->id])->save();
    [$calm] = shopInState('trial');
    $calm->forceFill(['name' => 'Rustig Cuts', 'referred_by_user_id' => $sam->id, 'trial_ends_at' => now()->addDays(10)])->save();

    $this->actingAs($sam);

    $page = Livewire::test(PitcherOverview::class)
        ->assertSee('2 zaken hebben aandacht nodig')
        ->assertSee('Bel hem voordat de proef afloopt')
        ->assertSee('Help hem een abonnement af te sluiten');

    $html = $page->html();
    expect(strpos($html, 'Bijna Cuts'))->toBeLessThan(strpos($html, 'Rustig Cuts'))
        ->and(strpos($html, 'Verlopen Cuts'))->toBeLessThan(strpos($html, 'Rustig Cuts'));
});

it('shows shops still signing up, and explains that paying is off while billing is off', function () {
    $sam = referralPitcher('Sam Bakker');
    [$shop] = shopWithOwner(['name' => 'Bezig Cuts', 'referred_by_user_id' => $sam->id, 'onboarding_step' => 4]);

    $this->actingAs($sam);

    Livewire::test(PitcherOverview::class)->assertSee('Bezig Cuts')->assertSee('Bezig met aanmelden')->assertSee('Betalen staat nog uit');
});

it('never shows revenue, customers or the owner email to a pitcher', function () {
    $sam = referralPitcher('Sam Bakker');
    [$shop, $owner] = shopWithOwner(['name' => 'Privé Cuts', 'referred_by_user_id' => $sam->id]);
    $owner->forceFill(['email' => 'eigenaar-geheim@example.com'])->save();

    $this->actingAs($sam);

    Livewire::test(PitcherOverview::class)->assertSee('Privé Cuts')->assertDontSee('eigenaar-geheim@example.com')->assertDontSee('Omzet')->assertDontSee('klanten');
});

it('links from the demo page to the overview with the number of shops', function () {
    $sam = referralPitcher('Sam Bakker');
    shopWithOwner(['referred_by_user_id' => $sam->id]);

    $this->actingAs($sam)->get('/demo')->assertOk()->assertSee('Mijn aanmeldingen')->assertSee('(1)');
});

it('reserves the pitcher path so no shop can take it', function () {
    expect(config('barbercuts.reserved_slugs'))->toContain('pitcher');
});

// ── Adminpanel ───────────────────────────────────────────────────────────────────────────────────────────

it('lets the admin link a shop to a pitcher afterwards, and remove the link, with a log entry', function () {
    Filament::setCurrentPanel('admin');
    app(CurrentShop::class)->disableScope();
    $admin = User::factory()->create(['is_admin' => true]);
    $sam = referralPitcher('Sam Bakker');
    [$shop] = shopWithOwner();

    Livewire::actingAs($admin)->test(ListBarbershops::class)
        ->callTableAction('assignPitcher', $shop, data: ['pitcher' => $sam->id])->assertNotified('Pitcher gekoppeld');

    expect($shop->fresh()->referred_by_user_id)->toBe($sam->id)
        ->and(ActivityLog::query()->where('action', 'shop.pitcher_assigned')->count())->toBe(1);

    Livewire::actingAs($admin)->test(ListBarbershops::class)
        ->callTableAction('assignPitcher', $shop, data: ['pitcher' => null])->assertNotified('Koppeling weggehaald');

    expect($shop->fresh()->referred_by_user_id)->toBeNull();
});

it('shows the pitcher per shop and counts sign-ups, trials and paying shops per pitcher', function () {
    billingOn();
    Filament::setCurrentPanel('admin');
    app(CurrentShop::class)->disableScope();
    $admin = User::factory()->create(['is_admin' => true]);
    $sam = referralPitcher('Sam Bakker');

    [$trial] = shopInState('trial');
    $trial->forceFill(['referred_by_user_id' => $sam->id])->save();
    [$paying] = shopInState('active');
    $paying->forceFill(['referred_by_user_id' => $sam->id])->save();

    Livewire::actingAs($admin)->test(ListBarbershops::class)->assertSee('Sam Bakker')
        ->filterTable('referred_by_user_id', $sam->id)->assertCanSeeTableRecords([$trial, $paying]);

    $record = PitcherResource::getEloquentQuery()->whereKey($sam->id)->first();

    expect($record->signups_count)->toBe(2)->and($record->trial_count)->toBe(1)->and($record->paying_count)->toBe(1);
});
