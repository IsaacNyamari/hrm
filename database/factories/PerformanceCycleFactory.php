<?php

namespace Database\Factories;

use App\Enums\PerformanceCycleStatus;
use App\Enums\PerformanceCycleType;
use App\Models\PerformanceCycle;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceCycle>
 */
class PerformanceCycleFactory extends Factory
{
    protected $model = PerformanceCycle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 months', 'now');

        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->year().' Annual review',
            'type' => PerformanceCycleType::Annual,
            'status' => PerformanceCycleStatus::Draft,
            'starts_on' => $start->format('Y-m-d'),
            'ends_on' => (clone $start)->modify('+3 months')->format('Y-m-d'),
            'self_review_enabled' => true,
            'peer_review_enabled' => false,
            'goal_weight' => 60,
            'competency_weight' => 40,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => PerformanceCycleStatus::Active]);
    }

    public function inReview(): static
    {
        return $this->state(fn (): array => ['status' => PerformanceCycleStatus::InReview]);
    }
}
