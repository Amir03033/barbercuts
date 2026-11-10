<?php

use App\Enums\ShopRole;
use App\Models\Barbershop;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\CurrentShop;
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
