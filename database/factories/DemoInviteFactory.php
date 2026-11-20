<?php

namespace Database\Factories;

use App\Models\DemoInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemoInvite>
 */
class DemoInviteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['is_pitcher' => true]),
            'shop_name' => fake()->randomElement(['Fadez', 'Barber Bros', 'Kapsalon Noor', 'The Cut Club']),
            'city' => fake()->randomElement(['Zwolle', 'Utrecht', 'Rotterdam']),
            'accent_color' => null,
        ];
    }
}
