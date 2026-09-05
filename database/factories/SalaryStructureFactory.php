<?php

namespace Database\Factories;

use App\Models\SalaryStructure;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryStructure>
 */
class SalaryStructureFactory extends Factory
{
    protected $model = SalaryStructure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->randomElement(['Junior Grade', 'Mid Grade', 'Senior Grade', 'Executive Grade']),
            'is_default' => false,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }
}
