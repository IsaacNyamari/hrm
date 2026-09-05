<?php

namespace Database\Factories;

use App\Models\JobPosition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobPosition>
 */
class JobPositionFactory extends Factory
{
    protected $model = JobPosition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();

        return [
            'tenant_id' => Tenant::factory(),
            'title' => $title,
            'code' => Str::upper(Str::slug($title, '_')).'_'.fake()->unique()->numerify('###'),
            'description' => fake()->paragraph(),
            'requirements' => fake()->paragraph(),
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }
}
