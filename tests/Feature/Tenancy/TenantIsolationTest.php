<?php

use App\Livewire\App\OpeningHours;
use App\Livewire\App\Services\Form as ServiceForm;
use App\Livewire\App\Services\Index as ServiceIndex;
use App\Livewire\App\Staff\Edit as StaffEdit;
use App\Livewire\App\Staff\Index as StaffIndex;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\OpeningHour;
use App\Models\Service;
use App\Models\ShopImage;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use App\Models\TimeOff;
use App\Support\CurrentShop;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
| De belangrijkste testset (docs/BOUWOPDRACHT.md §12): zaak A ziet nooit iets van zaak B.
*/

beforeEach(function () {
    [$this->shopA, $this->ownerA] = shopWithOwner(['name' => 'Zaak A']);
    [$this->shopB, $this->ownerB] = shopWithOwner(['name' => 'Zaak B']);

    foreach (['A' => $this->shopA, 'B' => $this->shopB] as $letter => $shop) {
        $this->{"staff{$letter}"} = StaffMember::factory()->create(['barbershop_id' => $shop->id, 'name' => "Kapper {$letter}"]);
        $this->{"service{$letter}"} = Service::factory()->create(['barbershop_id' => $shop->id, 'name' => "Dienst {$letter}"]);
        $this->{"customer{$letter}"} = Customer::factory()->create(['barbershop_id' => $shop->id, 'name' => "Klant {$letter}"]);
        $this->{"closure{$letter}"} = TimeOff::factory()->create(['barbershop_id' => $shop->id, 'reason' => "Sluiting {$letter}"]);
        $this->{"appointment{$letter}"} = Appointment::factory()->create([
            'barbershop_id' => $shop->id,
            'customer_id' => $this->{"customer{$letter}"}->id,
            'staff_member_id' => $this->{"staff{$letter}"}->id,
            'service_id' => $this->{"service{$letter}"}->id,
        ]);
    }
});

it('scopes every tenant model to the current shop', function (string $model) {
    inShop($this->shopA, function () use ($model) {
        expect($model::query()->pluck('barbershop_id')->unique()->all())->toBe([$this->shopA->id]);
    });

    inShop($this->shopB, function () use ($model) {
        expect($model::query()->pluck('barbershop_id')->unique()->all())->toBe([$this->shopB->id]);
    });
})->with([
    StaffMember::class,
    Service::class,
    Customer::class,
    Appointment::class,
    TimeOff::class,
    ShopSetting::class,
]);

it('returns nothing when no shop is set (fail closed)', function (string $model) {
    app(CurrentShop::class)->clear();

    expect($model::query()->count())->toBe(0);
})->with([
    StaffMember::class,
    Service::class,
    Customer::class,
    Appointment::class,
    TimeOff::class,
    ShopSetting::class,
    OpeningHour::class,
    ShopImage::class,
]);

it('cannot find a record of another shop by id', function () {
    inShop($this->shopA, function () {
        expect(Customer::find($this->customerB->id))->toBeNull()
            ->and(Appointment::find($this->appointmentB->id))->toBeNull()
            ->and(StaffMember::find($this->staffB->id))->toBeNull()
            ->and(Service::find($this->serviceB->id))->toBeNull();
    });
});

it('fills in the current shop when creating a record', function () {
    $customer = inShop($this->shopA, fn () => Customer::create(['name' => 'Nieuw', 'phone' => '+31612345678']));

    expect($customer->barbershop_id)->toBe($this->shopA->id);
});

it('does not update or delete records of another shop through a scoped query', function () {
    inShop($this->shopA, function () {
        Service::query()->update(['name' => 'Aangepast']);
        TimeOff::query()->delete();
    });

    expect($this->serviceB->fresh()->name)->toBe('Dienst B')
        ->and(TimeOff::withoutGlobalScopes()->whereKey($this->closureB->id)->exists())->toBeTrue();
});

it('gives a 404 for pages of records of another shop', function (string $path) {
    $path = str_replace(['{staffB}', '{serviceB}'], [$this->staffB->id, $this->serviceB->id], $path);

    $this->actingAs($this->ownerA)->get($path)->assertNotFound();
})->with([
    '/app/kappers/{staffB}',
    '/app/diensten/{serviceB}',
]);

it('shows pages of records of the own shop', function () {
    $this->actingAs($this->ownerA)->get("/app/kappers/{$this->staffA->id}")->assertOk()->assertSee('Kapper A');
    $this->actingAs($this->ownerA)->get("/app/diensten/{$this->serviceA->id}")->assertOk();
});

it('lists only records of the own shop', function () {
    actingInShop($this->ownerA, $this->shopA);

    Livewire::test(StaffIndex::class)->assertSee('Kapper A')->assertDontSee('Kapper B');
    Livewire::test(ServiceIndex::class)->assertSee('Dienst A')->assertDontSee('Dienst B');
    Livewire::test(OpeningHours::class)->assertSee('Sluiting A')->assertDontSee('Sluiting B');
});

it('refuses Livewire actions with ids of another shop', function () {
    actingInShop($this->ownerA, $this->shopA);

    // Een sluitingsdag van zaak B verwijderen
    Livewire::test(OpeningHours::class)->call('deleteTimeOff', $this->closureB->id)->assertNotFound();

    // Vrije dag van een kapper van zaak B verwijderen via de pagina van de eigen kapper
    $offB = TimeOff::factory()->create(['barbershop_id' => $this->shopB->id, 'staff_member_id' => $this->staffB->id]);

    Livewire::test(StaffEdit::class, ['staffMember' => $this->staffA->id])->call('deleteTimeOff', $offB->id)->assertNotFound();

    // De kapper-pagina openen met een id van zaak B
    Livewire::test(StaffEdit::class, ['staffMember' => $this->staffB->id])->assertNotFound();

    expect(TimeOff::withoutGlobalScopes()->whereKey([$this->closureB->id, $offB->id])->count())->toBe(2);
});

it('ignores staff ids of another shop when saving a service', function () {
    actingInShop($this->ownerA, $this->shopA);
    $secondStaffA = StaffMember::factory()->create(['barbershop_id' => $this->shopA->id]);

    Livewire::test(ServiceForm::class, ['service' => $this->serviceA->id])
        ->set('staffIds', [$this->staffA->id, $this->staffB->id])
        ->call('save')
        ->assertHasNoErrors();

    $linked = DB::table('service_staff_member')->where('service_id', $this->serviceA->id)->pluck('staff_member_id')->all();

    expect($linked)->toBe([$this->staffA->id]);
});

it('cannot tamper with the locked record id of a Livewire component', function () {
    actingInShop($this->ownerA, $this->shopA);

    Livewire::test(StaffEdit::class, ['staffMember' => $this->staffA->id])->set('staffId', $this->staffB->id);
})->throws(Exception::class);

it('opens the shop chosen in the session and never a shop the user is not a member of', function () {
    $this->actingAs($this->ownerA)
        ->withSession(['current_shop_id' => $this->shopB->id])
        ->get('/app')
        ->assertOk()
        ->assertSee('Zaak A')
        ->assertDontSee('Zaak B');
});

it('renders a page request only with records of the own shop', function () {
    $response = $this->actingAs($this->ownerA)->get('/app/kappers')->assertOk();

    expect($response->getContent())->toContain('Kapper A')->not->toContain('Kapper B');
});
