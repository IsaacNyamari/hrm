<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\GoalCheckIn;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoalCheckIn>
 */
class GoalCheckInFactory extends Factory
{
    protected $model = GoalCheckIn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'goal_id' => Goal::factory(),
            'progress' => fake()->numberBetween(10, 90),
            'note' => fake()->sentence(),
        ];
    }
}
