<?php

use App\Enums\ShopRole;
use App\Livewire\Onboarding;
use App\Models\Barbershop;
use App\Models\OpeningHour;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WorkingHour;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function onboardShop(User $user, string $name = 'JF Cuts'): Testable
{
    test()->actingAs($user);

    return Livewire::test(Onboarding::class)
        ->assertSet('step', 2)
        ->set('shopName', $name)
        ->set('city', 'Utrecht')
        ->set('phone', '06 12345678')
        ->call('saveShop')
        ->assertHasNoErrors()
        ->assertSet('step', 3);
}

it('starts with the account step for guests and creates the account', function () {
    Livewire::test(Onboarding::class)
        ->assertSet('step', 1)
        ->set('name', 'Jay')
        ->set('email', 'jay@example.com')
        ->set('password', 'geheim-wachtwoord')
        ->call('saveAccount')
        ->assertHasNoErrors()
        ->assertRedirect(route('onboarding'));

    $this->assertAuthenticatedAs(User::where('email', 'jay@example.com')->firstOrFail());

    Livewire::test(Onboarding::class)->assertSet('step', 2);
});

it('suggests a free slug from the shop name', function () {
    Barbershop::factory()->create(['slug' => 'jf-cuts']);
    $this->actingAs(User::factory()->create());

    Livewire::test(Onboarding::class)
        ->set('city', 'Utrecht')
        ->set('shopName', 'JF Cuts')
        ->assertSet('slug', 'jf-cuts-utrecht');
});

it('rejects reserved, taken and malformed slugs', function (string $slug) {
    Barbershop::factory()->create(['slug' => 'bezet']);
    $this->actingAs(User::factory()->create());

    Livewire::test(Onboarding::class)
        ->set('shopName', 'Mijn zaak')
        ->set('city', 'Utrecht')
        ->set('phone', '0612345678')
        ->set('slug', $slug)
        ->call('saveShop')
        ->assertHasErrors('slug');

    expect(Barbershop::where('name', 'Mijn zaak')->exists())->toBeFalse();
})->with(['admin', 'app', 'inloggen', 'livewire-abc', 'bezet', 'Hoofd Letters', 'a', 'met_underscore', '-streep']);

it('creates the shop with owner, settings and E.164 phone after step 2', function () {
    $user = User::factory()->create();
    onboardShop($user);

    $shop = Barbershop::where('slug', 'jf-cuts')->firstOrFail();

    expect($shop->phone)->toBe('+31612345678')
        ->and($shop->is_public)->toBeFalse()
        ->and($shop->onboarding_step)->toBe(3)
        ->and($shop->trial_ends_at)->toBeNull()
        ->and($user->roleIn($shop))->toBe(ShopRole::Owner)
        ->and(ShopSetting::withoutGlobalScopes()->where('barbershop_id', $shop->id)->count())->toBe(1);
});

it('gives a new shop a trial when billing is enabled', function () {
    config(['barbercuts.billing.enabled' => true]);
    onboardShop(User::factory()->create());

    $shop = Barbershop::where('slug', 'jf-cuts')->firstOrFail();

    expect((int) round(now()->diffInDays($shop->trial_ends_at)))->toBe(14);
});

it('rejects an invalid phone number', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Onboarding::class)
        ->set('shopName', 'JF Cuts')->set('city', 'Utrecht')->set('phone', '12345')
        ->call('saveShop')
        ->assertHasErrors('phone');
});

it('completes the whole onboarding for a solo barber', function () {
    $user = User::factory()->create(['name' => 'Jay Fontein']);

    onboardShop($user)
        ->call('saveBarbers')
        ->assertSet('step', 4)
        ->call('saveHours')
        ->assertSet('step', 5)
        ->set('services.3.selected', false)
        ->set('services.0.price', '27,50')
        ->call('saveServices')
        ->assertHasNoErrors()
        ->assertSet('step', 6)
        ->assertSee('jf-cuts');

    $shop = Barbershop::where('slug', 'jf-cuts')->firstOrFail();
    $staff = StaffMember::withoutGlobalScopes()->where('barbershop_id', $shop->id)->get();

    expect($shop->is_public)->toBeTrue()
        ->and($shop->onboarding_step)->toBeNull()
        ->and($staff)->toHaveCount(1)
        ->and($staff->first()->user_id)->toBe($user->id)
        ->and($staff->first()->name)->toBe('Jay Fontein')
        // di–za 09:00–18:00
        ->and(OpeningHour::withoutGlobalScopes()->where('barbershop_id', $shop->id)->pluck('day_of_week')->sort()->values()->all())->toBe([2, 3, 4, 5, 6])
        ->and(WorkingHour::where('staff_member_id', $staff->first()->id)->count())->toBe(5)
        ->and(Service::withoutGlobalScopes()->where('barbershop_id', $shop->id)->pluck('price_cents', 'name')->all())
        ->toBe(['Knippen' => 2750, 'Knippen + baard' => 3500, 'Baard' => 1500]);

    $this->get('/jf-cuts')->assertOk()->assertSee('JF Cuts');
});

it('creates an agenda for every barber in a team, with breaks', function () {
    $user = User::factory()->create(['name' => 'Jay']);

    onboardShop($user)
        ->set('mode', 'team')
        ->set('barbers', ['Sam', ' ', 'Mo'])
        ->call('saveBarbers')
        ->call('addBlock', 2)
        ->assertSet('week.2.blocks', [['from' => '09:00', 'to' => '12:30'], ['from' => '13:00', 'to' => '18:00']])
        ->call('saveHours')
        ->assertSet('step', 5);

    $shop = Barbershop::where('slug', 'jf-cuts')->firstOrFail();
    $staff = StaffMember::withoutGlobalScopes()->where('barbershop_id', $shop->id)->orderBy('sort_order')->get();

    expect($staff->pluck('name')->all())->toBe(['Jay', 'Sam', 'Mo'])
        ->and($staff->pluck('user_id')->all())->toBe([$user->id, null, null])
        ->and($staff->pluck('color')->unique())->toHaveCount(3)
        ->and(WorkingHour::where('staff_member_id', $staff[1]->id)->where('day_of_week', 2)->count())->toBe(2)
        ->and(OpeningHour::withoutGlobalScopes()->where('barbershop_id', $shop->id)->where('day_of_week', 2)->count())->toBe(2);
});

it('lets an owner who does not cut add only other barbers', function () {
    onboardShop(User::factory()->create())
        ->set('mode', 'team')->set('ownerCuts', false)->set('barbers', [''])
        ->call('saveBarbers')
        ->assertHasErrors('barbers.0')
        ->set('barbers', ['Sam'])
        ->call('saveBarbers')
        ->assertSet('step', 4);

    expect(StaffMember::withoutGlobalScopes()->pluck('name')->all())->toBe(['Sam']);
});

it('rejects invalid hours and requires at least one service', function () {
    onboardShop(User::factory()->create())
        ->call('saveBarbers')
        ->set('week.2.blocks.0.to', '08:00')
        ->call('saveHours')
        ->assertSet('step', 4)
        ->assertSee('De eindtijd moet na de begintijd liggen.')
        ->set('week.2.blocks.0.to', '18:00')
        ->call('saveHours')
        ->assertSet('step', 5)
        ->set('services.0.selected', false)->set('services.1.selected', false)
        ->set('services.2.selected', false)->set('services.3.selected', false)
        ->call('saveServices')
        ->assertHasErrors('services')
        ->assertSet('step', 5);
});

it('resumes at the step where the owner stopped', function () {
    $user = User::factory()->create();
    onboardShop($user)->call('saveBarbers');

    Livewire::test(Onboarding::class)->assertSet('step', 4)->assertSet('shopName', 'JF Cuts');

    $this->get('/app')->assertRedirect('/aanmelden');
});

it('keeps an unfinished shop private', function () {
    onboardShop(User::factory()->create());

    auth()->logout();

    $this->get('/jf-cuts')->assertNotFound();
});

it('sends owners with a finished shop to the app', function () {
    [, $owner] = shopWithOwner();

    $this->actingAs($owner)->get('/aanmelden')->assertRedirect('/app');
});

it('never touches staff or services of another shop while rebuilding during onboarding', function () {
    [$other] = shopWithOwner();
    StaffMember::factory()->create(['barbershop_id' => $other->id, 'name' => 'Blijft']);
    Service::factory()->create(['barbershop_id' => $other->id, 'name' => 'Blijft ook']);

    onboardShop(User::factory()->create())->call('saveBarbers')->call('saveBarbers')->call('saveHours')->call('saveServices');

    expect(StaffMember::withoutGlobalScopes()->where('barbershop_id', $other->id)->count())->toBe(1)
        ->and(Service::withoutGlobalScopes()->where('barbershop_id', $other->id)->count())->toBe(1);
});
