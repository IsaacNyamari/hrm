<?php

namespace Database\Factories;

use App\Enums\LeadKind;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => LeadKind::Quote,
            'status' => LeadStatus::New,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '07'.fake()->numerify('########'),
            'company' => fake()->company(),
            'employee_count' => fake()->numberBetween(5, 500),
            'plan_interest' => null,
            'message' => fake()->sentence(12),
        ];
    }

    public function contact(): static
    {
        return $this->state(fn (): array => ['kind' => LeadKind::Contact, 'employee_count' => null]);
    }
}
