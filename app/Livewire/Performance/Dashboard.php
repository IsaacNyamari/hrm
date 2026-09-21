<?php

declare(strict_types=1);

namespace App\Livewire\Performance;

use App\Enums\GoalCategory;
use App\Enums\GoalStatus;
use App\Enums\PerformanceCycleStatus;
use App\Enums\PerformanceCycleType;
use App\Exceptions\PerformanceException;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Goal;
use App\Models\Kpi;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Services\Performance\PerformanceService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    use BindsTenant;

    public string $title = '';

    public string $category = 'business';

    public ?string $cycleUuid = null;

    // Cycle management
    public bool $showCycleForm = false;

    public ?string $editingCycleUuid = null;

    /** @var array<string, mixed> */
    public array $cycleForm = [];

    // KPI management
    public bool $showKpiForm = false;

    /** @var array<string, mixed> */
    public array $kpiForm = [];

    public string $recordingKpiUuid = '';

    public string $kpiValue = '';

    // Review
    public string $reviewingUuid = '';

    /** @var array<string, string> */
    public array $reviewRatings = [];

    public string $reviewStrengths = '';

    public string $reviewImprovements = '';

    public string $reviewComments = '';

    public function createGoal(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string'],
        ]);

        $employee = auth()->user()?->employee;

        if ($employee === null) {
            Flux::toast(variant: 'danger', text: __('Your account is not linked to an employee record.'));

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
        Flux::toast(variant: 'success', text: __('Goal added.'));
    }

    public function deleteGoal(string $uuid): void
    {
        $goal = Goal::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('delete', $goal);

        $goal->delete();
        Flux::modal('delete-goal-'.$uuid)->close();
        Flux::toast(variant: 'success', text: __('Goal deleted.'));
    }

    // --- KPIs -----------------------------------------------------------

    public function startCreateKpi(): void
    {
        Gate::authorize('create', Kpi::class);
        $this->resetErrorBag();
        $this->kpiForm = ['name' => '', 'unit' => '', 'target_value' => ''];
        $this->showKpiForm = true;
    }

    public function saveKpi(): void
    {
        $data = $this->validate([
            'kpiForm.name' => ['required', 'string', 'max:200'],
            'kpiForm.unit' => ['nullable', 'string', 'max:40'],
            'kpiForm.target_value' => ['required', 'numeric'],
        ])['kpiForm'];

        $employee = auth()->user()?->employee;

        if ($employee === null) {
            Flux::toast(variant: 'danger', text: __('Your account is not linked to an employee record.'));

            return;
        }

        Kpi::query()->create([
            'employee_id' => $employee->getKey(),
            'name' => $data['name'],
            'unit' => $data['unit'] !== '' ? $data['unit'] : null,
            'target_value' => $data['target_value'],
            'current_value' => 0,
            'created_by' => auth()->id(),
        ]);

        $this->showKpiForm = false;
        Flux::toast(variant: 'success', text: __('KPI added.'));
    }

    public function startRecordKpi(string $uuid): void
    {
        $this->recordingKpiUuid = $uuid;
        $this->kpiValue = '';
        Flux::modal('record-kpi')->show();
    }

    public function recordKpi(PerformanceService $service): void
    {
        $kpi = Kpi::query()->where('uuid', $this->recordingKpiUuid)->first();

        if ($kpi === null || Gate::denies('update', $kpi)) {
            Flux::toast(variant: 'danger', text: __('You cannot update that KPI.'));

            return;
        }

        $data = $this->validate(['kpiValue' => ['required', 'numeric']]);

        $service->recordKpi($kpi, (float) $data['kpiValue']);
        $this->recordingKpiUuid = '';
        $this->kpiValue = '';
        Flux::modal('record-kpi')->close();
        Flux::toast(variant: 'success', text: __('KPI value recorded.'));
    }

    public function deleteKpi(string $uuid): void
    {
        $kpi = Kpi::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('delete', $kpi);

        $kpi->delete();
        Flux::modal('delete-kpi-'.$uuid)->close();
        Flux::toast(variant: 'success', text: __('KPI deleted.'));
    }

    // --- Performance cycles ----------------------------------------------

    public function startCreateCycle(): void
    {
        Gate::authorize('create', PerformanceCycle::class);
        $this->resetErrorBag();
        $this->editingCycleUuid = null;
        $this->cycleForm = $this->blankCycleForm();
        $this->showCycleForm = true;
    }

    public function startEditCycle(string $uuid): void
    {
        $cycle = PerformanceCycle::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $cycle);

        $this->resetErrorBag();
        $this->editingCycleUuid = $cycle->uuid;
        $this->cycleForm = [
            'name' => $cycle->name,
            'type' => $cycle->type->value,
            'starts_on' => $cycle->starts_on->toDateString(),
            'ends_on' => $cycle->ends_on->toDateString(),
            'goal_weight' => (string) $cycle->goal_weight,
            'competency_weight' => (string) $cycle->competency_weight,
            'self_review_enabled' => $cycle->self_review_enabled,
            'peer_review_enabled' => $cycle->peer_review_enabled,
        ];
        $this->showCycleForm = true;
    }

    public function saveCycle(): void
    {
        $data = $this->validate([
            'cycleForm.name' => ['required', 'string', 'max:150'],
            'cycleForm.type' => ['required', 'string'],
            'cycleForm.starts_on' => ['required', 'date'],
            'cycleForm.ends_on' => ['required', 'date', 'after_or_equal:cycleForm.starts_on'],
            'cycleForm.goal_weight' => ['required', 'integer', 'between:0,100'],
            'cycleForm.competency_weight' => ['required', 'integer', 'between:0,100'],
            'cycleForm.self_review_enabled' => ['boolean'],
            'cycleForm.peer_review_enabled' => ['boolean'],
        ])['cycleForm'];

        if ((int) $data['goal_weight'] + (int) $data['competency_weight'] !== 100) {
            Flux::toast(variant: 'danger', text: __('Goal weight and competency weight must add up to 100.'));

            return;
        }

        $attributes = [
            'name' => $data['name'],
            'type' => PerformanceCycleType::from($data['type']),
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'goal_weight' => (int) $data['goal_weight'],
            'competency_weight' => (int) $data['competency_weight'],
            'self_review_enabled' => (bool) ($data['self_review_enabled'] ?? false),
            'peer_review_enabled' => (bool) ($data['peer_review_enabled'] ?? false),
        ];

        if ($this->editingCycleUuid === null) {
            Gate::authorize('create', PerformanceCycle::class);
            PerformanceCycle::query()->create($attributes + [
                'status' => PerformanceCycleStatus::Draft,
                'created_by' => auth()->id(),
            ]);
            Flux::toast(variant: 'success', text: __('Performance cycle created.'));
        } else {
            $cycle = PerformanceCycle::query()->where('uuid', $this->editingCycleUuid)->firstOrFail();
            Gate::authorize('update', $cycle);

            if ($cycle->status->isClosed()) {
                Flux::toast(variant: 'danger', text: __('A closed cycle cannot be edited.'));

                return;
            }

            $cycle->update($attributes);
            Flux::toast(variant: 'success', text: __('Performance cycle updated.'));
        }

        $this->cycleForm = $this->blankCycleForm();
        $this->editingCycleUuid = null;
        $this->showCycleForm = false;
    }

    public function removeCycle(string $uuid): void
    {
        $cycle = PerformanceCycle::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('delete', $cycle);

        if ($cycle->status !== PerformanceCycleStatus::Draft) {
            Flux::toast(variant: 'danger', text: __('Only a draft cycle can be deleted.'));

            return;
        }

        $cycle->delete();
        Flux::modal('delete-cycle-'.$uuid)->close();
        Flux::toast(variant: 'success', text: __('Performance cycle deleted.'));
    }

    public function openCycle(string $uuid, PerformanceService $service): void
    {
        $cycle = PerformanceCycle::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $cycle);

        $created = $service->openCycle($cycle);
        Flux::toast(variant: 'success', text: __(':count review(s) created.', ['count' => $created]));
    }

    public function startReviewPhaseForCycle(string $uuid, PerformanceService $service): void
    {
        $cycle = PerformanceCycle::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $cycle);

        $service->startReviewPhase($cycle);
        Flux::toast(variant: 'success', text: __('Cycle moved to the review phase.'));
    }

    public function closeCycle(string $uuid, PerformanceService $service): void
    {
        $cycle = PerformanceCycle::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $cycle);

        try {
            $service->closeCycle($cycle);
            Flux::modal('close-cycle-'.$uuid)->close();
            Flux::toast(variant: 'success', text: __('Performance cycle closed.'));
        } catch (PerformanceException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function blankCycleForm(): array
    {
        return [
            'name' => '', 'type' => 'annual', 'starts_on' => '', 'ends_on' => '',
            'goal_weight' => '60', 'competency_weight' => '40',
            'self_review_enabled' => true, 'peer_review_enabled' => false,
        ];
    }

    // --- Reviews -----------------------------------------------------------

    public function startReview(string $uuid, PerformanceService $service): void
    {
        $review = PerformanceReview::query()->where('uuid', $uuid)->with('ratings')->firstOrFail();
        Gate::authorize('submit', $review);

        $this->reviewingUuid = $review->uuid;
        $this->reviewStrengths = (string) $review->strengths;
        $this->reviewImprovements = (string) $review->improvements;
        $this->reviewComments = (string) $review->comments;

        $existing = $review->ratings->pluck('rating', 'competency');
        $this->reviewRatings = collect($service->competencyFramework())
            ->mapWithKeys(fn (array $c) => [$c['code'] => (string) ($existing[$c['code']] ?? '')])
            ->all();

        Flux::modal('review-form')->show();
    }

    public function submitReview(PerformanceService $service): void
    {
        $review = PerformanceReview::query()->where('uuid', $this->reviewingUuid)->firstOrFail();
        Gate::authorize('submit', $review);

        $ratings = collect($this->reviewRatings)
            ->filter(fn ($v) => $v !== '')
            ->map(fn ($v, $code) => ['competency' => $code, 'rating' => (float) $v])
            ->values()
            ->all();

        $payload = [
            'strengths' => $this->reviewStrengths !== '' ? $this->reviewStrengths : null,
            'improvements' => $this->reviewImprovements !== '' ? $this->reviewImprovements : null,
            'comments' => $this->reviewComments !== '' ? $this->reviewComments : null,
            'ratings' => $ratings,
        ];

        try {
            $service->submitReview($review, $payload);
            $this->reviewingUuid = '';
            Flux::modal('review-form')->close();
            Flux::toast(variant: 'success', text: __('Review submitted.'));
        } catch (PerformanceException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function acknowledgeReview(string $uuid, PerformanceService $service): void
    {
        $review = PerformanceReview::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('acknowledge', $review);

        $service->acknowledgeReview($review, null);
        Flux::toast(variant: 'success', text: __('Review acknowledged.'));
    }

    public function render(PerformanceService $service): View
    {
        $user = auth()->user();
        $employee = $user?->employee;
        $canManageCycles = $user?->can('performance.manage');

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
            'cycleTypes' => PerformanceCycleType::cases(),
            'goals' => $goals,
            'kpis' => $kpis,
            'toComplete' => $toComplete,
            'aboutMe' => $aboutMe,
            'canManageCycles' => $canManageCycles,
            'competencyFramework' => $service->competencyFramework(),
        ]);
    }
}
