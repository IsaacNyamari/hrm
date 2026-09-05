<?php

namespace Database\Factories;

use App\Enums\VacancyStatus;
use App\Models\Tenant;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vacancy>
 */
class VacancyFactory extends Factory
{
    protected $model = Vacancy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();

        return [
            'tenant_id' => Tenant::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'),
            'headcount' => 1,
            'positions_filled' => 0,
            'status' => VacancyStatus::Draft,
            'description' => fake()->paragraphs(2, true),
            'requirements' => fake()->paragraph(),
            'location' => fake()->city(),
            'is_remote' => fake()->boolean(30),
            'salary_currency' => 'KES',
            'pipeline_stages' => ['applied', 'screening', 'interview', 'offer', 'hired'],
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['status' => VacancyStatus::Open, 'published_at' => now()]);
    }
}
