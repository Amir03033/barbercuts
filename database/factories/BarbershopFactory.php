<?php

namespace Database\Factories;

use App\Enums\ShopRole;
use App\Models\Barbershop;
use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Barbershop>
 */
class BarbershopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 99999),
            'name' => $name,
            'city' => fake()->city(),
            'phone' => '+316'.fake()->numerify('########'),
            'timezone' => 'Europe/Amsterdam',
            'is_public' => true,
            'onboarding_step' => null,
            'trial_ends_at' => now()->addDays(14),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Barbershop $shop): void {
            $settings = new ShopSetting;
            $settings->barbershop_id = $shop->id;
            $settings->save();
        });
    }

    /**
     * Zaak die nog in de onboarding zit.
     */
    public function onboarding(int $step = 3): static
    {
        return $this->state(['is_public' => false, 'onboarding_step' => $step]);
    }

    public function ownedBy(User $user): static
    {
        return $this->hasAttached($user, ['role' => ShopRole::Owner->value], 'users');
    }
}
