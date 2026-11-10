<?php

namespace Database\Factories;

use App\Enums\TimeOffType;
use App\Models\Barbershop;
use App\Models\TimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeOff>
 */
class TimeOffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barbershop_id' => Barbershop::factory(),
            'staff_member_id' => null,
            'starts_at' => now()->addWeek()->startOfDay(),
            'ends_at' => now()->addWeek()->endOfDay(),
            'type' => TimeOffType::Closed,
            'reason' => 'Vakantie',
        ];
    }
}
