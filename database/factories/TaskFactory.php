<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'priority' => TaskPriority::Medium,
            'status' => TaskStatus::Pending,
            'due_date' => now()->addDays(7)->toDateString(),
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->getKey(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
