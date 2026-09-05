<?php

namespace Database\Factories;

use App\Models\Designation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Designation>
 */
class DesignationFactory extends Factory
{
    protected $model = Designation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Software Engineer', 'Accountant', 'HR Manager', 'Sales Executive',
            'Operations Lead', 'Support Agent', 'Product Manager', 'Driver', 'Analyst',
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
