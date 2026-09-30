<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeploymentStatus;
use App\Enums\DeploymentType;
use App\Models\Deployment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deployment>
 */
class DeploymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => DeploymentType::Pull,
            'status' => DeploymentStatus::Succeeded,
            'branch' => 'master',
            'from_commit' => $this->faker->sha1(),
            'to_commit' => $this->faker->sha1(),
            'output' => '$ git fetch origin'."\n".'$ git reset --hard origin/master',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeploymentStatus::Running,
            'to_commit' => null,
            'output' => null,
            'finished_at' => null,
        ]);
    }
}
