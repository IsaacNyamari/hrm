<?php

namespace Database\Factories;

use App\Enums\InterviewMode;
use App\Enums\InterviewStatus;
use App\Models\Application;
use App\Models\Interview;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    protected $model = Interview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'application_id' => Application::factory(),
            'round' => 1,
            'mode' => InterviewMode::Video,
            'scheduled_at' => now()->addDays(3),
            'duration_minutes' => 45,
            'status' => InterviewStatus::Scheduled,
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
