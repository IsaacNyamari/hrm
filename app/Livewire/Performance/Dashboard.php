<?php

declare(strict_types=1);

namespace App\Livewire\Performance;

use App\Enums\GoalCategory;
use App\Enums\GoalStatus;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Goal;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Services\Performance\PerformanceService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    use BindsTenant;

    public string $title = '';

    public string $category = 'business';

    public ?string $cycleUuid = null;

    public ?string $notice = null;

    public function createGoal(): void
    {
        $this->notice = null;

        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string'],
        ]);

        $employee = auth()->user()?->employee;

        if ($employee === null) {
            $this->notice = 'Your account is not linked to an employee record.';

            return;
        }

        $cycleId = $this->cycleUuid !== null
            ? PerformanceCycle::query()->where('uuid', $this->cycleUuid)->value('id')
            : null;

        Goal::query()->create([
            'employee_id' => $employee->getKey(),
            'performance_cycle_id' => $cycleId,
            'title' => $this->title,
            'category' => GoalCategory::from($this->category),
            'status' => GoalStatus::Active,
            'created_by' => auth()->id(),
        ]);

        $this->reset(['title']);
        $this->notice = 'Goal added.';
    }

    public function render(PerformanceService $service): View
    {
        $user = auth()->user();
        $employee = $user?->employee;

        $goals = $employee !== null
            ? $employee->goals()->with('cycle:id,name')->latest('id')->get()
            : collect();

        $kpis = $employee !== null
            ? $employee->kpis()->latest('id')->get()
            : collect();

        $toComplete = $user !== null
            ? PerformanceReview::query()
                ->where('reviewer_id', $user->getKey())
                ->with(['employee:id,first_name,last_name', 'cycle:id,name,status'])
                ->latest('id')->get()
            : collect();

        $aboutMe = $employee !== null
            ? $employee->performanceReviews()
                ->with(['cycle:id,name,status', 'reviewer:id,name'])
                ->latest('id')->get()
            : collect();

        return view('livewire.performance.dashboard', [
            'hasEmployee' => $employee !== null,
            'cycles' => PerformanceCycle::query()->orderByDesc('starts_on')->get(),
            'categories' => GoalCategory::cases(),
            'goals' => $goals,
            'kpis' => $kpis,
            'toComplete' => $toComplete,
            'aboutMe' => $aboutMe,
        ]);
    }
}
