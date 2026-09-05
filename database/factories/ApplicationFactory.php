<?php

namespace Database\Factories;

use App\Enums\ApplicantSource;
use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Tenant;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vacancy_id' => Vacancy::factory(),
            'applicant_id' => Applicant::factory(),
            'stage' => 'applied',
            'status' => ApplicationStatus::Active,
            'source' => ApplicantSource::CareersSite,
            'applied_at' => now(),
            'stage_changed_at' => now(),
        ];
    }

    public function forVacancy(Vacancy $vacancy): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $vacancy->tenant_id,
            'vacancy_id' => $vacancy->getKey(),
            'applicant_id' => Applicant::factory()->forTenant($vacancy->tenant),
            'stage' => $vacancy->firstStage(),
        ]);
    }

    public function forApplicant(Applicant $applicant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->getKey(),
        ]);
    }
}
