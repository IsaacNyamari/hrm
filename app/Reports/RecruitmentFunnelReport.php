<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Vacancy;

class RecruitmentFunnelReport extends BaseReport
{
    public function key(): string
    {
        return 'recruitment-funnel';
    }

    public function name(): string
    {
        return 'Recruitment funnel';
    }

    public function category(): string
    {
        return 'Recruitment';
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return ['recruitment.view'];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required?: bool}>
     */
    public function filters(): array
    {
        return [
            ['key' => 'vacancy_uuid', 'label' => 'Vacancy', 'type' => 'text'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        return ['Vacancy', 'Stage', 'Active', 'Hired', 'Rejected'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<int, array<int, string|int|float|null>>
     */
    public function rows(array $filters): iterable
    {
        $vacancyUuid = $this->str($filters, 'vacancy_uuid');

        $vacancies = Vacancy::query()
            ->when($vacancyUuid !== null, fn ($q) => $q->where('uuid', $vacancyUuid))
            ->get(['id', 'tenant_id', 'title', 'pipeline_stages']);

        $applications = Application::query()
            ->whereIn('vacancy_id', $vacancies->pluck('id')->all())
            ->get(['vacancy_id', 'stage', 'status'])
            ->groupBy('vacancy_id');

        $rows = [];
        foreach ($vacancies as $vacancy) {
            $forVacancy = $applications->get($vacancy->id) ?? collect();

            foreach ($vacancy->stages() as $stage) {
                $atStage = $forVacancy->where('stage', $stage);
                $rows[] = [
                    $vacancy->title,
                    $stage,
                    $atStage->where('status', ApplicationStatus::Active)->count(),
                    $atStage->where('status', ApplicationStatus::Hired)->count(),
                    $atStage->where('status', ApplicationStatus::Rejected)->count(),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int|float>
     */
    public function summary(array $filters): array
    {
        return [
            'Open vacancies' => Vacancy::query()->where('status', 'open')->count(),
            'Active applications' => Application::query()->where('status', 'active')->count(),
            'Hired' => Application::query()->where('status', 'hired')->count(),
        ];
    }
}
