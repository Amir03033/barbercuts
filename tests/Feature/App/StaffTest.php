<?php

use App\Enums\ShopRole;
use App\Enums\TimeOffType;
use App\Livewire\AcceptInvitation;
use App\Livewire\App\Staff\Edit as StaffEdit;
use App\Livewire\App\Staff\Index as StaffIndex;
use App\Models\Appointment;
use App\Models\OpeningHour;
use App\Models\StaffMember;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use App\Notifications\StaffInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    [$this->shop, $this->owner] = shopWithOwner();
    $this->staff = StaffMember::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Sam']);
});

it('adds a barber who works during the opening hours of the shop', function () {
    inShop($this->shop, fn () => OpeningHour::create(['day_of_week' => 3, 'opens_at' => '10:00', 'closes_at' => '17:00']));
    actingInShop($this->owner, $this->shop);

    Livewire::test(StaffIndex::class)->set('name', 'Mo')->call('add')->assertHasNoErrors();

    $mo = StaffMember::withoutGlobalScopes()->where('name', 'Mo')->firstOrFail();

    expect($mo->barbershop_id)->toBe($this->shop->id)
        ->and($mo->user_id)->toBeNull()
        ->and($mo->color)->not->toBe($this->staff->color)
        ->and(WorkingHour::where('staff_member_id', $mo->id)->get(['day_of_week', 'starts_at', 'ends_at'])->toArray())
        ->toBe([['day_of_week' => 3, 'starts_at' => '10:00:00', 'ends_at' => '17:00:00']]);
});

it('invites a barber by e-mail with a signed link that is valid for 7 days', function () {
    Notification::fake();
    actingInShop($this->owner, $this->shop);

    Livewire::test(StaffIndex::class)->set('name', 'Mo')->set('email', 'mo@example.com')->call('add')->assertHasNoErrors();

    $mo = StaffMember::withoutGlobalScopes()->where('name', 'Mo')->firstOrFail();
    expect($mo->invited_email)->toBe('mo@example.com');

    Notification::assertSentTo(new AnonymousNotifiable, StaffInvitation::class, function (StaffInvitation $notification, array $channels, AnonymousNotifiable $notifiable) {
        parse_str((string) parse_url($notification->url(), PHP_URL_QUERY), $query);

        return $notifiable->routes['mail'] === 'mo@example.com'
            && (int) $query['expires'] === now()->addDays(7)->timestamp
            && isset($query['signature']);
    });
});

it('lets an invited barber create an account and links it to the agenda', function () {
    $this->staff->forceFill(['invited_email' => 'sam@example.com', 'invited_at' => now()])->save();
    $url = URL::temporarySignedRoute('invitation.accept', now()->addDays(7), ['staffMember' => $this->staff->id, 'email' => 'sam@example.com']);

    $this->get($url)->assertOk()->assertSee($this->shop->name);

    Livewire::withQueryParams(['email' => 'sam@example.com'])
        ->test(AcceptInvitation::class, ['staffMember' => $this->staff->id])
        ->set('password', 'geheim-wachtwoord')
        ->call('accept')
        ->assertHasNoErrors()
        ->assertRedirect(route('app.today'));

    $user = User::where('email', 'sam@example.com')->firstOrFail();

    expect($user->roleIn($this->shop))->toBe(ShopRole::Barber)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($this->staff->fresh()->user_id)->toBe($user->id)
        ->and($this->staff->fresh()->invited_email)->toBeNull();

    $this->assertAuthenticatedAs($user);
});

it('rejects an invitation link without a valid signature', function () {
    $this->staff->forceFill(['invited_email' => 'sam@example.com'])->save();

    $this->get("/uitnodiging/{$this->staff->id}?email=sam@example.com")->assertForbidden();
    $this->get(URL::temporarySignedRoute('invitation.accept', now()->subMinute(), ['staffMember' => $this->staff->id, 'email' => 'sam@example.com']))->assertForbidden();
});

it('does not accept an invitation for another address or an already linked barber', function (Closure $prepare) {
    $prepare($this->staff);

    Livewire::withQueryParams(['email' => 'sam@example.com'])
        ->test(AcceptInvitation::class, ['staffMember' => $this->staff->id])
        ->assertSee('niet meer geldig')
        ->set('password', 'geheim-wachtwoord')
        ->call('accept')
        ->assertForbidden();

    expect(User::where('email', 'sam@example.com')->exists())->toBeFalse();
})->with([
    'invited with another address' => [fn (StaffMember $staff) => $staff->forceFill(['invited_email' => 'ander@example.com'])->save()],
    'not invited' => [fn (StaffMember $staff) => null],
    'already linked' => [fn (StaffMember $staff) => $staff->forceFill(['invited_email' => 'sam@example.com', 'user_id' => User::factory()->create()->id])->save()],
]);

it('does not let a logged in user accept an invitation for someone else', function () {
    $this->staff->forceFill(['invited_email' => 'sam@example.com'])->save();
    $this->actingAs(User::factory()->create(['email' => 'iemand@example.com']));

    Livewire::withQueryParams(['email' => 'sam@example.com'])
        ->test(AcceptInvitation::class, ['staffMember' => $this->staff->id])
        ->assertSee('ander account')
        ->call('accept')
        ->assertForbidden();

    expect($this->staff->fresh()->user_id)->toBeNull();
});

it('saves a profile and working hours with a break', function () {
    actingInShop($this->owner, $this->shop);

    Livewire::test(StaffEdit::class, ['staffMember' => $this->staff->id])
        ->set('name', 'Sam Bakker')
        ->set('color', '#dc2626')
        ->set('isActive', false)
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->set('week.1.open', true)
        ->set('week.1.blocks', [['from' => '09:00', 'to' => '12:30'], ['from' => '13:00', 'to' => '18:00']])
        ->call('saveHours');

    $staff = $this->staff->fresh();

    expect($staff->name)->toBe('Sam Bakker')
        ->and($staff->color)->toBe('#dc2626')
        ->and($staff->is_active)->toBeFalse()
        ->and(WorkingHour::where('staff_member_id', $staff->id)->orderBy('starts_at')->get(['day_of_week', 'starts_at', 'ends_at'])->toArray())->toBe([
            ['day_of_week' => 1, 'starts_at' => '09:00:00', 'ends_at' => '12:30:00'],
            ['day_of_week' => 1, 'starts_at' => '13:00:00', 'ends_at' => '18:00:00'],
        ]);
});

it('rejects overlapping blocks and a colour outside the palette', function () {
    actingInShop($this->owner, $this->shop);

    Livewire::test(StaffEdit::class, ['staffMember' => $this->staff->id])
        ->set('color', '#123456')
        ->call('saveProfile')
        ->assertHasErrors('color')
        ->set('week.1.open', true)
        ->set('week.1.blocks', [['from' => '09:00', 'to' => '13:00'], ['from' => '12:00', 'to' => '18:00']])
        ->call('saveHours')
        ->assertSee('De blokken overlappen elkaar.');

    expect(WorkingHour::where('staff_member_id', $this->staff->id)->count())->toBe(0);
});

it('stores holidays as whole days in the timezone of the shop', function () {
    $this->travelTo('2026-07-01 12:00');
    actingInShop($this->owner, $this->shop);

    Livewire::test(StaffEdit::class, ['staffMember' => $this->staff->id])
        ->set('offFrom', '2026-07-20')->set('offTo', '2026-07-24')->set('offReason', 'Vakantie')
        ->call('addTimeOff')
        ->assertHasNoErrors()
        ->assertSee('20 jul t/m 24 jul 2026');

    $off = TimeOff::withoutGlobalScopes()->where('staff_member_id', $this->staff->id)->firstOrFail();

    // Zomertijd: Amsterdam = UTC+2
    expect($off->barbershop_id)->toBe($this->shop->id)
        ->and($off->type)->toBe(TimeOffType::Closed)
        ->and($off->starts_at->toDateTimeString())->toBe('2026-07-19 22:00:00')
        ->and($off->ends_at->toDateTimeString())->toBe('2026-07-24 22:00:00');
});

it('stores custom hours for one day', function () {
    $this->travelTo('2026-12-01 12:00');
    actingInShop($this->owner, $this->shop);

    Livewire::test(StaffEdit::class, ['staffMember' => $this->staff->id])
        ->set('offType', 'custom_hours')->set('offFrom', '2026-12-17')->set('offStart', '12:00')->set('offEnd', '21:00')
        ->call('addTimeOff')
        ->assertHasNoErrors();

    $off = TimeOff::withoutGlobalScopes()->where('staff_member_id', $this->staff->id)->firstOrFail();

    // Wintertijd: Amsterdam = UTC+1
    expect($off->type)->toBe(TimeOffType::CustomHours)
        ->and($off->starts_at->toDateTimeString())->toBe('2026-12-17 11:00:00')
        ->and($off->ends_at->toDateTimeString())->toBe('2026-12-17 20:00:00');
});

it('rejects time off in the past or with an end before the start', function () {
    $this->travelTo('2026-07-01 12:00');
    actingInShop($this->owner, $this->shop);

    Livewire::test(StaffEdit::class, ['staffMember' => $this->staff->id])
        ->set('offFrom', '2026-06-01')->call('addTimeOff')->assertHasErrors('offFrom')
        ->set('offFrom', '2026-07-20')->set('offTo', '2026-07-10')->call('addTimeOff')->assertHasErrors('offTo');

    expect(TimeOff::withoutGlobalScopes()->count())->toBe(0);
});

it('lets a barber manage the own working hours and time off', function () {
    [$barber, $barberStaff] = barberIn($this->shop);
    $this->travelTo('2026-07-01 12:00');
    actingInShop($barber, $this->shop);

    Livewire::test(StaffEdit::class, ['staffMember' => $barberStaff->id])
        ->set('week.5.open', true)
        ->call('saveHours')
        ->set('offFrom', '2026-07-20')
        ->call('addTimeOff')
        ->assertHasNoErrors();

    expect(WorkingHour::where('staff_member_id', $barberStaff->id)->count())->toBe(1)
        ->and(TimeOff::withoutGlobalScopes()->where('staff_member_id', $barberStaff->id)->count())->toBe(1);
});

it('deletes a barber without appointments but keeps one with appointments', function () {
    actingInShop($this->owner, $this->shop);
    $busy = StaffMember::factory()->create(['barbershop_id' => $this->shop->id]);
    Appointment::factory()->create(['barbershop_id' => $this->shop->id, 'staff_member_id' => $busy->id]);

    Livewire::test(StaffEdit::class, ['staffMember' => $busy->id])->call('delete')->assertNoRedirect();
    Livewire::test(StaffEdit::class, ['staffMember' => $this->staff->id])->call('delete')->assertRedirect(route('app.staff'));

    expect(StaffMember::withoutGlobalScopes()->pluck('id')->all())->toContain($busy->id)->not->toContain($this->staff->id);
});

it('never deletes the last barber of a shop', function () {
    actingInShop($this->owner, $this->shop);

    Livewire::test(StaffEdit::class, ['staffMember' => $this->staff->id])->call('delete')->assertNoRedirect();

    expect($this->staff->fresh())->not->toBeNull();
});
