<?php

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+3 weeks');
        $end = (clone $start)->modify('+2 days');

        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'half_day_start' => false,
            'half_day_end' => false,
            'days' => 3,
            'reason' => fake()->sentence(),
            'status' => LeaveStatus::Pending,
            'current_level' => 1,
            'submitted_at' => now(),
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->getKey(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => LeaveStatus::Approved, 'decided_at' => now()]);
    }
}
