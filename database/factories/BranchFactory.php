<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->city();

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $city.' Office',
            'code' => Str::upper(Str::substr($city, 0, 3)).fake()->unique()->numerify('##'),
            'city' => $city,
            'country' => 'KE',
            'is_headquarters' => false,
            'is_active' => true,
        ];
    }

    public function headquarters(): static
    {
        return $this->state(fn (): array => ['is_headquarters' => true, 'name' => 'Head Office']);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }
}
