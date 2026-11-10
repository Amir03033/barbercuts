<?php

use App\Livewire\App\Services\Form as ServiceForm;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffMember;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    [$this->shop, $this->owner] = shopWithOwner();
    $this->sam = StaffMember::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Sam']);
    $this->mo = StaffMember::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Mo']);
    actingInShop($this->owner, $this->shop);
});

it('creates a service with the price in cents', function () {
    Livewire::test(ServiceForm::class)
        ->set('name', 'Knippen')->set('duration', '30')->set('price', '27,50')->set('bufferAfter', '5')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('app.services'));

    $service = Service::withoutGlobalScopes()->where('name', 'Knippen')->firstOrFail();

    expect($service->barbershop_id)->toBe($this->shop->id)
        ->and($service->price_cents)->toBe(2750)
        ->and($service->duration)->toBe(30)
        ->and($service->buffer_after)->toBe(5)
        ->and($service->is_active)->toBeTrue()
        // Alle kappers gekozen = geen koppeling (iedereen doet de dienst)
        ->and(DB::table('service_staff_member')->where('service_id', $service->id)->count())->toBe(0);
});

it('links a service to chosen barbers only', function () {
    $service = Service::factory()->create(['barbershop_id' => $this->shop->id]);

    Livewire::test(ServiceForm::class, ['service' => $service->id])
        ->assertSet('staffIds', [$this->sam->id, $this->mo->id])
        ->set('staffIds', [$this->mo->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('service_staff_member')->where('service_id', $service->id)->pluck('staff_member_id')->all())->toBe([$this->mo->id]);

    Livewire::test(ServiceForm::class, ['service' => $service->id])->assertSet('staffIds', [$this->mo->id]);
});

it('validates a service', function (array $input, string $field) {
    Livewire::test(ServiceForm::class)
        ->set('name', $input['name'] ?? 'Knippen')
        ->set('duration', $input['duration'] ?? '30')
        ->set('price', $input['price'] ?? '25')
        ->set('staffIds', $input['staffIds'] ?? [$this->sam->id])
        ->call('save')
        ->assertHasErrors($field);

    expect(Service::withoutGlobalScopes()->count())->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'too short' => [['duration' => '2'], 'duration'],
    'duration not a number' => [['duration' => 'half uur'], 'duration'],
    'price not a number' => [['price' => 'gratis'], 'price'],
    'negative price' => [['price' => '-5'], 'price'],
    'no barbers' => [['staffIds' => []], 'staffIds'],
]);

it('deletes a service without appointments but keeps one with appointments', function () {
    $free = Service::factory()->create(['barbershop_id' => $this->shop->id]);
    $used = Service::factory()->create(['barbershop_id' => $this->shop->id]);
    Appointment::factory()->create(['barbershop_id' => $this->shop->id, 'service_id' => $used->id, 'staff_member_id' => $this->sam->id]);

    Livewire::test(ServiceForm::class, ['service' => $free->id])->call('delete')->assertRedirect(route('app.services'));
    Livewire::test(ServiceForm::class, ['service' => $used->id])->call('delete')->assertNoRedirect();

    expect(Service::withoutGlobalScopes()->pluck('id')->all())->toBe([$used->id]);
});
