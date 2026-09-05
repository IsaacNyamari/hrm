<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\GoalCategory;
use App\Enums\GoalStatus;
use App\Facades\Tenancy;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceCycle;
use App\Services\Performance\PerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class GoalController extends Controller
{
    public function __construct(private readonly PerformanceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Goal::class);

        $user = $request->user();
        $canViewAll = $user?->can('performance.view') || $user?->can('performance.manage');
        $ownEmployeeId = $user?->employee?->getKey();
        $managedIds = $ownEmployeeId === null ? [] : Employee::query()->where('manager_id', $ownEmployeeId)->pluck('id')->all();

        $goals = Goal::query()
            ->with(['employee:id,first_name,last_name,employee_number', 'cycle:id,name,status'])
            ->when(! $canViewAll, function ($q) use ($ownEmployeeId, $managedIds): void {
                $q->where(fn ($inner) => $inner->where('employee_id', $ownEmployeeId)->orWhereIn('employee_id', $managedIds));
            })
            ->when($request->filled('employee_id') && $canViewAll, fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('cycle_uuid'), fn ($q) => $q->whereRelation('cycle', 'uuid', $request->string('cycle_uuid')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->get();

        return response()->json(['data' => $goals->map(fn (Goal $g): array => $this->transform($g))]);
    }

    public function show(Goal $goal): JsonResponse
    {
        $this->authorize('view', $goal);

        return response()->json(['data' => $this->transform($goal->load(['employee', 'cycle', 'checkIns']), detailed: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Goal::class);

        $data = $request->validate([
            'employee_id' => ['nullable', 'integer'],
            'cycle_uuid' => ['nullable', Rule::exists('performance_cycles', 'uuid')->where('tenant_id', Tenancy::id())],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', new Enum(GoalCategory::class)],
            'weight' => ['sometimes', 'integer', 'between:0,100'],
            'metric' => ['nullable', 'string', 'max:200'],
            'target' => ['nullable', 'string', 'max:200'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $employee = $this->resolveEmployee($request, $data['employee_id'] ?? null);
        $cycle = $this->resolveCycle($data['cycle_uuid'] ?? null);

        $goal = Goal::query()->create([
            'employee_id' => $employee->getKey(),
            'performance_cycle_id' => $cycle?->getKey(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'],
            'weight' => (int) ($data['weight'] ?? 0),
            'metric' => $data['metric'] ?? null,
            'target' => $data['target'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => GoalStatus::Active,
            'created_by' => $request->user()?->getKey(),
        ]);

        return response()->json(['data' => $this->transform($goal->fresh(), detailed: true)], 201);
    }

    public function update(Request $request, Goal $goal): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['sometimes', new Enum(GoalCategory::class)],
            'weight' => ['sometimes', 'integer', 'between:0,100'],
            'metric' => ['nullable', 'string', 'max:200'],
            'target' => ['nullable', 'string', 'max:200'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', new Enum(GoalStatus::class)],
            'progress' => ['sometimes', 'integer', 'between:0,100'],
            'self_rating' => ['sometimes', 'nullable', 'numeric', 'between:0,5'],
            'manager_rating' => ['sometimes', 'nullable', 'numeric', 'between:0,5'],
            'manager_comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $managerFields = array_intersect_key($data, array_flip(['manager_rating', 'manager_comment']));
        $selfFields = array_diff_key($data, $managerFields);

        if ($selfFields !== []) {
            $this->authorize('update', $goal);
            $goal->fill($selfFields);
        }

        if ($managerFields !== []) {
            $this->authorize('rate', $goal);
            $goal->fill($managerFields);
        }

        $goal->save();

        return response()->json(['data' => $this->transform($goal->fresh(), detailed: true)]);
    }

    public function destroy(Goal $goal): Response
    {
        $this->authorize('delete', $goal);

        $goal->delete();

        return response()->noContent();
    }

    public function checkIn(Request $request, Goal $goal): JsonResponse
    {
        $this->authorize('update', $goal);

        $data = $request->validate([
            'progress' => ['required', 'integer', 'between:0,100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $actor = $request->user();
        abort_if($actor === null, 401);

        $checkIn = $this->service->checkInGoal($goal, (int) $data['progress'], $data['note'] ?? null, $actor);

        return response()->json(['data' => [
            'uuid' => $checkIn->uuid,
            'progress' => $checkIn->progress,
            'note' => $checkIn->note,
            'goal' => $this->transform($goal->fresh(), detailed: true),
        ]], 201);
    }

    private function resolveEmployee(Request $request, ?int $employeeId): Employee
    {
        if ($employeeId !== null) {
            abort_unless($request->user()?->can('performance.manage'), 403);

            return Employee::query()->findOrFail($employeeId);
        }

        $employee = $request->user()?->employee;
        abort_if($employee === null, 422, 'Your account is not linked to an employee record.');

        return $employee;
    }

    private function resolveCycle(?string $cycleUuid): ?PerformanceCycle
    {
        if ($cycleUuid === null) {
            return null;
        }

        return PerformanceCycle::query()->where('uuid', $cycleUuid)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Goal $goal, bool $detailed = false): array
    {
        $base = [
            'uuid' => $goal->uuid,
            'title' => $goal->title,
            'category' => $goal->category->value,
            'status' => $goal->status->value,
            'weight' => $goal->weight,
            'progress' => $goal->progress,
            'self_rating' => $goal->self_rating,
            'manager_rating' => $goal->manager_rating,
            'employee' => $goal->relationLoaded('employee') ? $goal->employee->fullName() : null,
            'cycle' => $goal->relationLoaded('cycle') ? $goal->cycle?->name : null,
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'description' => $goal->description,
            'metric' => $goal->metric,
            'target' => $goal->target,
            'start_date' => $goal->start_date?->toDateString(),
            'due_date' => $goal->due_date?->toDateString(),
            'manager_comment' => $goal->manager_comment,
            'check_ins' => $goal->relationLoaded('checkIns')
                ? $goal->checkIns->map(fn ($c): array => [
                    'uuid' => $c->uuid,
                    'progress' => $c->progress,
                    'note' => $c->note,
                    'logged_at' => $c->created_at?->toIso8601String(),
                ])->all()
                : null,
        ];
    }
}
