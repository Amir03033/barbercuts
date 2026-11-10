<?php

namespace Database\Factories;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDay()->setTime(10, 0);

        return [
            'barbershop_id' => Barbershop::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()->state(['barbershop_id' => $attributes['barbershop_id']]),
            'staff_member_id' => fn (array $attributes) => StaffMember::factory()->state(['barbershop_id' => $attributes['barbershop_id']]),
            'service_id' => fn (array $attributes) => Service::factory()->state(['barbershop_id' => $attributes['barbershop_id']]),
            'service_name' => 'Knippen',
            'duration' => 30,
            'price_cents' => 2500,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'blocked_until' => $startsAt->copy()->addMinutes(30),
            'status' => AppointmentStatus::Scheduled,
            'source' => AppointmentSource::Online,
            'manage_token' => Str::random(40),
        ];
    }
}
