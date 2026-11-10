<?php

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Livewire\App\Customers\Show as CustomerShow;
use App\Livewire\App\Revenue;
use App\Livewire\App\Today;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\ShopStatsService;
use App\Support\Period;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
| Omzet en statistieken (docs/BOUWOPDRACHT.md §6.4, §7.5, B9). "Nu" is woensdag 18 november 2026, 20:00.
| Sam (kapper met account) en Mo werken ma–za 09:00–18:00.
*/

beforeEach(function () {
    $this->travelTo(at('2026-11-18 20:00'));

    [$this->shop, $this->owner] = shopWithOwner();
    [$this->samUser, $this->sam] = barberIn($this->shop, 'Sam');
    setWorkingHours($this->sam);
    $this->mo = staffWithHours($this->shop, 'Mo', sortOrder: 1);
    $this->service = Service::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Knippen']);
    $this->daan = Customer::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Daan']);
    $this->yusuf = Customer::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Yusuf']);
    $this->stats = app(ShopStatsService::class);

    $this->visit = fn (string $start, $staff, int $paid = 2500, int $tip = 0, PaymentMethod $method = PaymentMethod::Pin, ?Customer $customer = null, AppointmentStatus $status = AppointmentStatus::Completed, int $minutes = 30, $shop = null) => Appointment::factory()->create([
        'barbershop_id' => ($shop ?? $this->shop)->id,
        'staff_member_id' => $staff->id,
        'service_id' => $this->service->id,
        'customer_id' => ($customer ?? $this->daan)->id,
        'duration' => $minutes,
        'starts_at' => at($start),
        'ends_at' => at($start)->addMinutes($minutes),
        'blocked_until' => at($start)->addMinutes($minutes),
        'status' => $status,
        'amount_paid_cents' => $status === AppointmentStatus::Completed ? $paid : null,
        'tip_cents' => $status === AppointmentStatus::Completed ? $tip : null,
        'payment_method' => $status === AppointmentStatus::Completed ? $method : null,
        'completed_at' => $status === AppointmentStatus::Completed ? at($start)->addMinutes($minutes) : null,
    ]);

    $this->week = Period::make('week', '2026-11-18', 'Europe/Amsterdam');

    // Deze week: Sam € 25 + € 35 (fooi € 2), Mo € 40 contant
    ($this->visit)('2026-11-16 10:00', $this->sam, 2500, 200);
    ($this->visit)('2026-11-18 11:00', $this->sam, 3500, 0, PaymentMethod::Tikkie, $this->yusuf);
    ($this->visit)('2026-11-17 14:00', $this->mo, 4000, 0, PaymentMethod::Cash, $this->yusuf, minutes: 60);
});

it('calculates the kpis of a period', function () {
    ($this->visit)('2026-11-17 09:00', $this->sam, status: AppointmentStatus::NoShow);
    ($this->visit)('2026-11-17 16:00', $this->sam, status: AppointmentStatus::Cancelled);
    ($this->visit)('2026-11-20 10:00', $this->mo, status: AppointmentStatus::Scheduled);

    $kpis = $this->stats->kpis($this->shop, $this->owner, $this->week);

    expect($kpis['revenue_cents'])->toBe(10000)
        ->and($kpis['tips_cents'])->toBe(200)                     // apart, niet in de omzet
        ->and($kpis['appointments'])->toBe(3)                     // alleen voltooid
        ->and($kpis['average_cents'])->toBe(3333)
        ->and($kpis['no_shows'])->toBe(1)
        ->and($kpis['no_show_rate'])->toBe(0.25)                  // 1 / (3 + 1)
        ->and($kpis['booked_minutes'])->toBe(150)                 // voltooid 120 + gepland 30; geen no-show, geen annulering
        ->and($kpis['available_minutes'])->toBe(2 * 6 * 9 * 60)   // 2 kappers, ma–za, 9 uur
        ->and(round($kpis['occupancy'], 4))->toBe(round(150 / 6480, 4))
        ->and($kpis['by_payment_method'])->toEqualCanonicalizing(['pin' => 2500, 'tikkie' => 3500, 'cash' => 4000]);
});

it('counts new customers by their first completed visit and the return rate over 90 days', function () {
    // Daan was er ook vorige maand: geen nieuwe klant deze week, wel een terugkerende
    ($this->visit)('2026-10-20 10:00', $this->sam);
    // Lars alleen lang geleden: telt niet mee in de 90 dagen
    ($this->visit)('2026-06-01 10:00', $this->sam, customer: Customer::factory()->create(['barbershop_id' => $this->shop->id]));

    $kpis = $this->stats->kpis($this->shop, $this->owner, $this->week);

    expect($kpis['new_customers'])->toBe(1)             // Yusuf
        ->and($kpis['return_rate'])->toBe(1.0);          // Daan (2×) en Yusuf (2×) van de 2 klanten
});

it('compares with the previous period', function () {
    ($this->visit)('2026-11-10 10:00', $this->sam, 5000);

    $comparison = $this->stats->revenueWithComparison($this->shop, $this->owner, $this->week);

    expect($comparison)->toBe(['revenue_cents' => 10000, 'previous_cents' => 5000, 'change' => 1.0]);

    $empty = $this->stats->revenueWithComparison($this->shop, $this->owner, Period::make('week', '2026-11-11', 'Europe/Amsterdam'));

    expect($empty['change'])->toBeNull();
});

it('puts revenue in the right day in the timezone of the shop', function () {
    // Zaterdag 23:30 in Amsterdam is zaterdag 22:30 UTC; zondag 00:30 is zaterdag 23:30 UTC
    ($this->visit)('2026-11-21 23:30', $this->sam, 1100);
    ($this->visit)('2026-11-22 00:30', $this->sam, 1300);

    $series = collect($this->stats->series($this->shop, $this->owner, $this->week))->pluck('cents', 'label')->all();

    expect($series)->toBe(['ma' => 2500, 'di' => 4000, 'wo' => 3500, 'do' => 0, 'vr' => 0, 'za' => 1100, 'zo' => 1300]);

    $nextWeek = $this->stats->revenueWithComparison($this->shop, $this->owner, $this->week->next());
    expect($nextWeek['revenue_cents'])->toBe(0);
});

it('builds the chart in a single query', function () {
    DB::enableQueryLog();
    $this->stats->series($this->shop, $this->owner, Period::make('month', '2026-11-18', 'Europe/Amsterdam'));
    DB::disableQueryLog();

    expect(DB::getQueryLog())->toHaveCount(2);   // rol van de kijker + de grafiek
});

// ── Omzetzichtbaarheid (B9) ──────────────────────────────────────────────────────────────────────────────

it('shows the owner the total and a breakdown per barber', function () {
    $perStaff = collect($this->stats->perStaff($this->shop, $this->owner, $this->week))
        ->mapWithKeys(fn (array $row) => [$row['staff']->name => [$row['revenue_cents'], $row['tips_cents'], $row['appointments']]])
        ->all();

    expect($this->stats->kpis($this->shop, $this->owner, $this->week)['revenue_cents'])->toBe(10000)
        ->and($perStaff)->toBe(['Sam' => [6000, 200, 2], 'Mo' => [4000, 0, 1]])
        // De eigenaar mag op één kapper filteren
        ->and($this->stats->kpis($this->shop, $this->owner, $this->week, $this->mo->id)['revenue_cents'])->toBe(4000);
});

it('shows a barber only the own revenue, whatever staff id is requested', function (?Closure $requested) {
    $staffId = $requested ? $requested($this) : null;

    expect($this->stats->scopeFor($this->shop, $this->samUser, $staffId))->toBe($this->sam->id)
        ->and($this->stats->kpis($this->shop, $this->samUser, $this->week, $staffId)['revenue_cents'])->toBe(6000)
        ->and($this->stats->revenueWithComparison($this->shop, $this->samUser, $this->week, $staffId)['revenue_cents'])->toBe(6000)
        ->and(array_sum(array_column($this->stats->series($this->shop, $this->samUser, $this->week, $staffId), 'cents')))->toBe(6000)
        ->and($this->stats->perStaff($this->shop, $this->samUser, $this->week))->toBe([]);
})->with([
    'nothing' => [null],
    'a colleague' => [fn ($test) => $test->mo->id],
    'a made-up id' => [fn ($test) => 999999],
]);

it('does not let a barber see the revenue of a colleague by manipulating the request', function () {
    actingInShop($this->samUser, $this->shop);

    Livewire::withQueryParams(['kapper' => $this->mo->id, 'periode' => 'week', 'datum' => '2026-11-18'])
        ->test(Revenue::class)
        ->assertSee('Mijn omzet')
        ->assertSee('€ 60,00')
        ->assertDontSee('€ 100,00')->assertDontSee('€ 40,00')
        ->assertDontSee('Per kapper')
        ->set('staffId', $this->mo->id)
        ->assertSee('€ 60,00')
        ->assertDontSee('€ 40,00');
});

it('shows the owner the total and lets him filter on a barber', function () {
    actingInShop($this->owner, $this->shop);

    Livewire::withQueryParams(['periode' => 'week', 'datum' => '2026-11-18'])
        ->test(Revenue::class)
        ->assertSee('€ 100,00')
        ->assertSeeInOrder(['Per kapper', 'Sam', '€ 60,00', 'Mo', '€ 40,00'])
        ->set('staffId', $this->mo->id)
        ->assertSee('€ 40,00')
        ->assertDontSee('€ 100,00');
});

it('shows nothing to a barber without an own calendar or to an outsider', function () {
    $noCalendar = User::factory()->create();
    $this->shop->users()->attach($noCalendar->id, ['role' => 'barber']);

    expect($this->stats->kpis($this->shop, $noCalendar, $this->week)['revenue_cents'])->toBe(0)
        ->and($this->stats->kpis($this->shop, User::factory()->create(), $this->week)['revenue_cents'])->toBe(0)
        ->and($this->stats->kpis($this->shop, shopWithOwner()[1], $this->week)['revenue_cents'])->toBe(0);
});

it('shows the own revenue of today on the today screen', function () {
    ($this->visit)('2026-11-18 15:00', $this->mo, 9900);

    actingInShop($this->samUser, $this->shop);
    Livewire::test(Today::class)->assertSee('Mijn omzet vandaag')->assertSee('€ 35,00')->assertDontSee('€ 99,00');

    actingInShop($this->owner, $this->shop);
    Livewire::test(Today::class)->assertSee('Omzet vandaag')->assertSee('€ 134,00');
});

it('shows a barber only what a customer spent with him', function () {
    expect($this->stats->customerSpend($this->shop, $this->owner, $this->yusuf))->toBe(7500)
        ->and($this->stats->customerSpend($this->shop, $this->samUser, $this->yusuf))->toBe(3500);

    actingInShop($this->samUser, $this->shop);
    Livewire::test(CustomerShow::class, ['customer' => $this->yusuf->id])->assertSee('Bij mij besteed')->assertSee('€ 35,00')->assertDontSee('€ 75,00');

    actingInShop($this->owner, $this->shop);
    Livewire::test(CustomerShow::class, ['customer' => $this->yusuf->id])->assertSee('Totaal besteed')->assertSee('€ 75,00');
});

it('never includes revenue of another shop', function () {
    [$other, $otherOwner] = shopWithOwner();
    $otherStaff = staffWithHours($other, 'Buurman');
    ($this->visit)('2026-11-17 10:00', $otherStaff, 99900, shop: $other);

    expect($this->stats->kpis($this->shop, $this->owner, $this->week)['revenue_cents'])->toBe(10000)
        // De eigenaar van een andere zaak ziet hier niets, ook niet met een id van een kapper van deze zaak
        ->and($this->stats->kpis($this->shop, $otherOwner, $this->week, $this->sam->id)['revenue_cents'])->toBe(0)
        // En deze eigenaar ziet niets van de kapper van de buren
        ->and($this->stats->kpis($this->shop, $this->owner, $this->week, $otherStaff->id)['revenue_cents'])->toBe(0);
});

// ── CSV-export ───────────────────────────────────────────────────────────────────────────────────────────

it('exports the completed appointments of a month as csv for the owner', function () {
    ($this->visit)('2026-11-17 09:00', $this->sam, status: AppointmentStatus::NoShow);
    ($this->visit)('2026-10-30 10:00', $this->sam, 1111);

    $response = $this->actingAs($this->owner)->get('/app/omzet/export?maand=2026-11')->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=utf-8')
        ->assertDownload("omzet-{$this->shop->slug}-2026-11.csv");

    $lines = explode("\n", trim(str_replace("\xEF\xBB\xBF", '', $response->streamedContent())));

    expect($lines)->toBe([
        'Datum;Tijd;Klant;Dienst;Kapper;Bedrag;Fooi;Betaalmethode',
        '16-11-2026;10:00;Daan;Knippen;Sam;25,00;2,00;Pin',
        '17-11-2026;14:00;Yusuf;Knippen;Mo;40,00;0,00;Contant',
        '18-11-2026;11:00;Yusuf;Knippen;Sam;35,00;0,00;Tikkie',
    ]);
});

it('exports only the own rows for a barber', function () {
    $content = $this->actingAs($this->samUser)->get('/app/omzet/export?maand=2026-11')->assertOk()->streamedContent();

    expect($content)->toContain(';Sam;')->not->toContain(';Mo;')->not->toContain('40,00');
});

it('requires a login for the export and never exports another shop', function () {
    $this->get('/app/omzet/export?maand=2026-11')->assertRedirect('/inloggen');

    [, $otherOwner] = shopWithOwner();
    $content = $this->actingAs($otherOwner)->get('/app/omzet/export?maand=2026-11')->assertOk()->streamedContent();

    expect($content)->not->toContain('Daan')->not->toContain('Yusuf');
});
