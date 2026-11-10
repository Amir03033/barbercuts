<?php

use App\Enums\ShopRole;
use App\Models\Barbershop;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Een afgeronde zaak met eigenaar.
 *
 * @param  array<string, mixed>  $attributes
 * @return array{0: Barbershop, 1: User}
 */
function shopWithOwner(array $attributes = []): array
{
    $owner = User::factory()->create();
    $shop = Barbershop::factory()->ownedBy($owner)->create($attributes);

    return [$shop, $owner];
}

/**
 * Een kapper met eigen account en agenda in deze zaak.
 *
 * @return array{0: User, 1: StaffMember}
 */
function barberIn(Barbershop $shop, string $name = 'Mo'): array
{
    $user = User::factory()->create(['name' => $name]);
    $shop->users()->attach($user->id, ['role' => ShopRole::Barber->value]);

    $staff = StaffMember::factory()->create(['barbershop_id' => $shop->id, 'user_id' => $user->id, 'name' => $name]);

    return [$user, $staff];
}

/**
 * Log in en zet de zaak, zoals de middleware van /app dat doet. Voor Livewire::test().
 */
function actingInShop(User $user, Barbershop $shop): void
{
    test()->actingAs($user);
    session(['current_shop_id' => $shop->id]);
    app(CurrentShop::class)->set($shop);
}

/**
 * Voer code uit binnen een zaak (voor het klaarzetten en controleren van gegevens).
 *
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function inShop(Barbershop $shop, Closure $callback): mixed
{
    return app(CurrentShop::class)->run($shop, $callback);
}

/**
 * Zet de werktijden van een kapper: [weekdag => [[van, tot], …]]. Standaard ma–za 09:00–18:00.
 *
 * @param  array<int, list<array{0: string, 1: string}>>|null  $days
 */
function setWorkingHours(StaffMember $staff, ?array $days = null): void
{
    $days ??= array_fill_keys([1, 2, 3, 4, 5, 6], [['09:00', '18:00']]);

    $staff->workingHours()->delete();

    foreach ($days as $day => $blocks) {
        foreach ($blocks as [$from, $to]) {
            $staff->workingHours()->create(['day_of_week' => $day, 'starts_at' => $from, 'ends_at' => $to]);
        }
    }
}

/**
 * Een kapper met werktijden in deze zaak.
 *
 * @param  array<int, list<array{0: string, 1: string}>>|null  $days
 */
function staffWithHours(Barbershop $shop, string $name = 'Sam', ?array $days = null, int $sortOrder = 0): StaffMember
{
    $staff = StaffMember::factory()->create(['barbershop_id' => $shop->id, 'name' => $name, 'sort_order' => $sortOrder]);
    setWorkingHours($staff, $days);

    return $staff;
}

/**
 * Een tijdstip in Amsterdam, als UTC (zoals het in de database staat).
 */
function at(string $local): CarbonImmutable
{
    return CarbonImmutable::parse($local, 'Europe/Amsterdam')->utc();
}

/**
 * Starttijden als kloktijden in Amsterdam: ['09:00', '09:15', …].
 *
 * @param  list<CarbonImmutable>  $slots
 * @return list<string>
 */
function clock(array $slots): array
{
    return array_map(fn (CarbonImmutable $slot): string => $slot->setTimezone('Europe/Amsterdam')->format('H:i'), $slots);
}
