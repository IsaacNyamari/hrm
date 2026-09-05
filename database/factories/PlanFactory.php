<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'price_cents' => fake()->numberBetween(1, 20) * 100000,
            'currency' => 'KES',
            'billing_interval' => BillingInterval::Monthly,
            'employee_limit' => 50,
            'storage_limit_mb' => 5120,
            'features' => ['flags' => ['hr_core', 'attendance', 'leave', 'reports'], 'limits' => []],
            'trial_days' => 14,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (): array => ['price_cents' => 0, 'trial_days' => 0]);
    }

    public function unlimited(): static
    {
        return $this->state(fn (): array => ['employee_limit' => null, 'storage_limit_mb' => null]);
    }
}
