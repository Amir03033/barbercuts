<?php

use App\Enums\ShopRole;
use App\Livewire\App\Staff\Edit as StaffEdit;
use App\Livewire\App\Staff\Index as StaffIndex;
use App\Livewire\App\Today;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    [$this->shop, $this->owner] = shopWithOwner();
    [$this->barber, $this->barberStaff] = barberIn($this->shop, 'Mo');
    $this->colleague = StaffMember::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Sam']);
});

it('knows the role of a user in a shop', function () {
    [$otherShop] = shopWithOwner();

    expect($this->owner->roleIn($this->shop))->toBe(ShopRole::Owner)
        ->and($this->barber->roleIn($this->shop))->toBe(ShopRole::Barber)
        ->and($this->owner->roleIn($otherShop))->toBeNull()
        ->and(User::factory()->create()->roleIn($this->shop))->toBeNull();
});

it('lets one user be owner of one shop and barber in another', function () {
    [$otherShop] = shopWithOwner();
    $otherShop->users()->attach($this->owner->id, ['role' => ShopRole::Barber->value]);

    expect($this->owner->roleIn($this->shop))->toBe(ShopRole::Owner)
        ->and($this->owner->roleIn($otherShop))->toBe(ShopRole::Barber);
});

it('keeps owner-only screens away from barbers', function (string $path) {
    $this->actingAs($this->barber)->get($path)->assertForbidden();
    $this->actingAs($this->owner)->get($path)->assertOk();
})->with([
    '/app/diensten',
    '/app/diensten/nieuw',
    '/app/openingstijden',
    '/app/uiterlijk',
    '/app/instellingen',
]);

it('lets a barber open the app, the menu and the staff list', function (string $path) {
    $this->actingAs($this->barber)->get($path)->assertOk();
})->with(['/app', '/app/meer', '/app/kappers']);

it('lets a barber edit only the own working hours', function () {
    $this->actingAs($this->barber)->get("/app/kappers/{$this->barberStaff->id}")->assertOk()->assertDontSee('Profiel opslaan');
    $this->actingAs($this->barber)->get("/app/kappers/{$this->colleague->id}")->assertForbidden();
});

it('does not let a barber change a profile, invite or delete', function (string $action) {
    actingInShop($this->barber, $this->shop);

    Livewire::test(StaffEdit::class, ['staffMember' => $this->barberStaff->id])
        ->set('name', 'Andere naam')
        ->set('inviteEmail', 'iemand@example.com')
        ->call($action)
        ->assertForbidden();

    expect($this->barberStaff->fresh()->name)->toBe('Mo');
})->with(['saveProfile', 'invite', 'delete']);

it('does not let a barber add staff', function () {
    actingInShop($this->barber, $this->shop);

    Livewire::test(StaffIndex::class)->set('name', 'Nieuw')->call('add')->assertForbidden();

    expect(StaffMember::withoutGlobalScopes()->where('name', 'Nieuw')->exists())->toBeFalse();
});

it('does not let a barber mark the booking link as shared', function () {
    actingInShop($this->barber, $this->shop);

    Livewire::test(Today::class)->call('markLinkShared')->assertForbidden();
});

it('applies the appointment policy: owner all, barber only the own calendar', function () {
    $customer = Customer::factory()->create(['barbershop_id' => $this->shop->id]);
    $service = Service::factory()->create(['barbershop_id' => $this->shop->id]);
    $make = fn (StaffMember $staff) => Appointment::factory()->create([
        'barbershop_id' => $this->shop->id, 'customer_id' => $customer->id, 'service_id' => $service->id, 'staff_member_id' => $staff->id,
    ]);

    $own = $make($this->barberStaff);
    $other = $make($this->colleague);
    $outsider = User::factory()->create();

    expect($this->owner->can('update', $own))->toBeTrue()
        ->and($this->owner->can('update', $other))->toBeTrue()
        ->and($this->barber->can('update', $own))->toBeTrue()
        ->and($this->barber->can('update', $other))->toBeFalse()
        ->and($this->barber->can('view', $other))->toBeTrue()
        ->and($outsider->can('view', $own))->toBeFalse()
        ->and($outsider->can('update', $own))->toBeFalse();
});

it('applies the customer policy: members see and edit, only the owner deletes', function () {
    $customer = Customer::factory()->create(['barbershop_id' => $this->shop->id]);
    [, $otherOwner] = shopWithOwner();

    expect($this->barber->can('view', $customer))->toBeTrue()
        ->and($this->barber->can('update', $customer))->toBeTrue()
        ->and($this->barber->can('delete', $customer))->toBeFalse()
        ->and($this->owner->can('delete', $customer))->toBeTrue()
        ->and($otherOwner->can('view', $customer))->toBeFalse();
});
