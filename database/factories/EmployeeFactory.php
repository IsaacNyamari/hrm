<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => null,
            'employee_number' => 'EMP-'.fake()->unique()->numerify('######'),
            'first_name' => fake()->firstName(),
            'middle_name' => null,
            'last_name' => fake()->lastName(),
            'preferred_name' => null,
            'email' => fake()->unique()->safeEmail(),
            'personal_email' => fake()->optional()->safeEmail(),
            'phone' => '+2547'.fake()->numerify('########'),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases()),
            'marital_status' => fake()->randomElement(MaritalStatus::cases()),
            'nationality' => 'Kenyan',
            'city' => fake()->city(),
            'country' => 'KE',
            'job_title' => fake()->jobTitle(),
            'hire_date' => fake()->dateTimeBetween('-6 years', 'now')->format('Y-m-d'),
            'status' => EmploymentStatus::Active,
            'salary_amount_cents' => fake()->numberBetween(300, 5000) * 1000,
            'salary_currency' => 'KES',
            'pay_frequency' => 'monthly',
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function status(EmploymentStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function terminated(): static
    {
        return $this->state(fn (): array => [
            'status' => EmploymentStatus::Terminated,
            'exit_date' => now()->subDays(fake()->numberBetween(1, 200))->toDateString(),
        ]);
    }
}
