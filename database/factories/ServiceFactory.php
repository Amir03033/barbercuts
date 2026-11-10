<?php

namespace Database\Factories;

use App\Models\Barbershop;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barbershop_id' => Barbershop::factory(),
            'name' => fake()->randomElement(['Knippen', 'Knippen + baard', 'Baard', 'Kinderen']),
            'duration' => 30,
            'price_cents' => 2500,
            'buffer_after' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
