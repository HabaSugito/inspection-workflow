<?php

namespace Database\Factories;

use App\Models\Inspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inspection>
 */
class InspectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_name' => fake()->name(),
            'vehicle_registration' => fake()->bothify('??## ???'),
            'requested_date' => fake()->dateTimeBetween('today', '+30 days')->format('Y-m-d'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
