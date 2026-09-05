<?php

namespace Database\Factories;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $principal = fake()->numberBetween(50, 500) * 100000;
        $installments = fake()->numberBetween(3, 12);

        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'type' => LoanType::Loan,
            'principal_cents' => $principal,
            'interest_rate' => 0,
            'installments' => $installments,
            'installment_cents' => (int) ceil($principal / $installments),
            'outstanding_cents' => $principal,
            'start_date' => now()->subMonth()->toDateString(),
            'status' => LoanStatus::Active,
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->getKey(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => LoanStatus::Pending]);
    }
}
