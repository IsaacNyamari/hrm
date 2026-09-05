<?php

namespace Database\Factories;

use App\Enums\ExpenseStatus;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'reference' => 'EXP-'.fake()->unique()->numerify('######'),
            'employee_id' => Employee::factory(),
            'title' => fake()->randomElement(['Client visit — Mombasa', 'Team lunch', 'Conference tickets', 'Taxi fares']),
            'currency' => 'KES',
            'total_cents' => 0,
            'status' => ExpenseStatus::Draft,
            'incurred_on' => now()->subDays(3)->toDateString(),
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->getKey(),
        ]);
    }
}
