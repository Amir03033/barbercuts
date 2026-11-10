<?php

namespace Database\Factories;

use App\Models\Barbershop;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barbershop_id' => Barbershop::factory(),
            'name' => fake()->name(),
            'phone' => '+316'.fake()->unique()->numerify('########'),
            'email' => fake()->optional()->safeEmail(),
        ];
    }
}
