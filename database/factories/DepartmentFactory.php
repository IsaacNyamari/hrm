<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Human Resources', 'Finance', 'Engineering', 'Sales', 'Marketing',
            'Operations', 'Customer Support', 'Legal', 'Procurement', 'IT',
        ]);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'code' => Str::upper(Str::substr(str_replace(' ', '', $name), 0, 4)),
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }
}
