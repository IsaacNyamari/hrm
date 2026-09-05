<?php

namespace Database\Factories;

use App\Enums\HrRequestStatus;
use App\Enums\HrRequestType;
use App\Models\Employee;
use App\Models\HrRequest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HrRequest>
 */
class HrRequestFactory extends Factory
{
    protected $model = HrRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'reference' => 'HR-'.fake()->unique()->numerify('######'),
            'type' => fake()->randomElement(HrRequestType::cases()),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'priority' => 'normal',
            'status' => HrRequestStatus::Open,
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
