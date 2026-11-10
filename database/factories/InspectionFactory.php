<?php

namespace Database\Factories;

use App\Enums\InspectionStatus;
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

    public function assigned(): static
    {
        return $this->state(fn () => [
            'status' => InspectionStatus::Assigned,
            'inspector_name' => fake()->name(),
            'assigned_at' => now()->subDays(2),
        ]);
    }

    public function inProgress(): static
    {
        return $this->assigned()->state(fn () => [
            'status' => InspectionStatus::InProgress,
            'started_at' => now()->subDay(),
        ]);
    }

    public function completed(): static
    {
        return $this->inProgress()->state(fn () => [
            'status' => InspectionStatus::Completed,
            'completed_at' => now()->subHour(),
        ]);
    }
}
