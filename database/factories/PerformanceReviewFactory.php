<?php

namespace Database\Factories;

use App\Enums\ReviewerType;
use App\Enums\ReviewStatus;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceReview>
 */
class PerformanceReviewFactory extends Factory
{
    protected $model = PerformanceReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'performance_cycle_id' => PerformanceCycle::factory(),
            'employee_id' => Employee::factory(),
            'reviewer_type' => ReviewerType::Manager,
            'status' => ReviewStatus::Pending,
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->getKey(),
        ]);
    }

    public function forCycle(PerformanceCycle $cycle): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $cycle->tenant_id,
            'performance_cycle_id' => $cycle->getKey(),
        ]);
    }

    public function selfReview(): static
    {
        return $this->state(fn (): array => ['reviewer_type' => ReviewerType::SelfReview]);
    }
}
