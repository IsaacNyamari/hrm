<?php

namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Models\Application;
use App\Models\Offer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'application_id' => Application::factory(),
            'job_title' => fake()->jobTitle(),
            'salary_cents' => fake()->numberBetween(300, 2500) * 1000,
            'salary_currency' => 'KES',
            'start_date' => now()->addWeeks(4)->toDateString(),
            'status' => OfferStatus::Draft,
        ];
    }

    public function forApplication(Application $application): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $application->tenant_id,
            'application_id' => $application->getKey(),
        ]);
    }
}
