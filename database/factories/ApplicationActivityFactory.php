<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationActivity>
 */
class ApplicationActivityFactory extends Factory
{
    protected $model = ApplicationActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'application_id' => Application::factory(),
            'type' => 'note',
            'note' => fake()->sentence(),
        ];
    }
}
