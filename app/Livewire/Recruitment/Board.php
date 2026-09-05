<?php

declare(strict_types=1);

namespace App\Livewire\Recruitment;

use App\Livewire\Concerns\BindsTenant;
use App\Models\Application;
use App\Models\Vacancy;
use App\Services\Recruitment\RecruitmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Board extends Component
{
    use BindsTenant;

    public ?string $vacancyUuid = null;

    public ?string $notice = null;

    public function mount(): void
    {
        $this->vacancyUuid ??= Vacancy::query()->where('status', 'open')->value('uuid')
            ?? Vacancy::query()->value('uuid');
    }

    public function moveStage(string $applicationUuid, string $toStage, RecruitmentService $service): void
    {
        $this->notice = null;
        $application = Application::query()->where('uuid', $applicationUuid)->first();

        if ($application === null || Gate::denies('manage', $application)) {
            $this->notice = 'You cannot move that candidate.';

            return;
        }

        $service->moveStage($application, $toStage, auth()->user());
        $this->notice = 'Candidate moved to '.$toStage.'.';
    }

    public function render(): View
    {
        $vacancies = Vacancy::query()->orderByDesc('id')->get(['uuid', 'title', 'status']);
        $vacancy = $this->vacancyUuid !== null
            ? Vacancy::query()->where('uuid', $this->vacancyUuid)->first()
            : null;

        $columns = [];
        if ($vacancy !== null) {
            $applications = $vacancy->applications()
                ->with('applicant:id,first_name,last_name')
                ->orderByDesc('id')
                ->get();

            foreach ($vacancy->stages() as $stage) {
                $columns[$stage] = $applications->where('stage', $stage)->values();
            }
        }

        return view('livewire.recruitment.board', [
            'vacancies' => $vacancies,
            'vacancy' => $vacancy,
            'columns' => $columns,
        ]);
    }
}
