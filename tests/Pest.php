<?php

use App\Enums\ShopRole;
use App\Models\Barbershop;
use App\Models\StaffMember;
use App\Models\Subscription;
use App\Models\User;
use App\Support\CurrentShop;
use App\Support\PlatformSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Platforminstellingen worden per proces onthouden; begin elke test schoon.
    ->beforeEach(fn () => PlatformSettings::forget())
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

/**
 * Zet facturering aan voor deze test (standaard staat het uit, B12). Het standaardplan staat al in de database.
 */
function billingOn(): void
{
    config(['barbercuts.billing.enabled' => true]);
}

/**
 * Een abonnement van een zaak in een bepaalde status.
 *
 * @param  array<string, mixed>  $attributes
 */
function subscriptionFor(Barbershop $shop, string $status = 'active', array $attributes = []): Subscription
{
    $subscription = new Subscription;
    $subscription->barbershop_id = $shop->id;
    $subscription->status = $status;
    $subscription->price_cents = 1000;
    $subscription->current_period_ends_at = now()->addMonth();
    $subscription->forceFill($attributes);
    $subscription->save();

    return $subscription;
}

/**
 * Een betaalde zaak met eigenaar, in een bepaalde toegangsstatus (facturering moet aan staan).
 *
 * @return array{0: Barbershop, 1: User}
 */
function shopInState(string $state): array
{
    [$shop, $owner] = shopWithOwner(['trial_ends_at' => null]);

    match ($state) {
        'trial' => $shop->forceFill(['trial_ends_at' => now()->addDays(10)])->save(),
        'free' => $shop->forceFill(['free_until' => now()->addMonths(3)])->save(),
        'active' => subscriptionFor($shop, 'active'),
        'past_due' => subscriptionFor($shop, 'past_due', ['past_due_at' => now()->subDays(2)]),
        'suspended' => $shop->forceFill(['suspended_at' => now(), 'suspended_reason' => 'Test'])->save(),
        default => null, // expired
    };

    return [$shop->fresh(), $owner];
}
