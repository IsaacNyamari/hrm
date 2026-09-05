<?php

namespace Database\Factories;

use App\Enums\KpiDirection;
use App\Models\Employee;
use App\Models\Kpi;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kpi>
 */
class KpiFactory extends Factory
{
    protected $model = Kpi::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'name' => fake()->randomElement(['Tickets resolved', 'Revenue booked', 'NPS', 'Defect rate', 'On-time delivery']),
            'unit' => fake()->randomElement(['count', '%', 'KES', 'days']),
            'direction' => KpiDirection::HigherIsBetter,
            'target_value' => 100,
            'current_value' => fake()->numberBetween(0, 120),
            'weight' => fake()->numberBetween(10, 40),
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
