<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Exceptions\TaskException;
use App\Facades\Tenancy;
use App\Models\Employee;
use App\Models\Task;
use App\Services\Tasks\TaskService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $user = $request->user();
        $canOversee = $user?->can('tasks.view') || $user?->can('tasks.assign') || $user?->can('tasks.manage');
        $ownEmployeeId = $user?->employee?->getKey();

        $records = Task::query()
            ->with(['employee:id,first_name,last_name', 'assignedByUser:id,name'])
            ->when(! $canOversee, fn ($q) => $q->where('employee_id', $ownEmployeeId))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('employee_id') && $canOversee, fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json($records->through(fn (Task $t): array => $this->transform($t))->toArray());
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return response()->json(['data' => $this->transform($task->load(['employee', 'assignedByUser']), detailed: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $data = $this->validated($request);
        $employee = Employee::query()->where('uuid', $data['employee_uuid'])->firstOrFail();

        $task = $this->service->assign(
            $employee,
            $data['title'],
            $data['description'] ?? null,
            TaskPriority::from($data['priority']),
            isset($data['due_date']) ? CarbonImmutable::parse((string) $data['due_date']) : null,
            $request->user(),
        );

        return response()->json(['data' => $this->transform($task->load('employee'), detailed: true)], 201);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $data = $this->validated($request, requireTitle: false);

        if (isset($data['employee_uuid'])) {
            $employee = Employee::query()->where('uuid', $data['employee_uuid'])->firstOrFail();

            try {
                $this->service->reassign($task, $employee);
            } catch (TaskException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        $this->service->update($task, [
            'title' => $data['title'] ?? $task->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $task->description,
            'priority' => isset($data['priority']) ? TaskPriority::from($data['priority']) : $task->priority,
            'due_date' => array_key_exists('due_date', $data)
                ? ($data['due_date'] !== null ? CarbonImmutable::parse((string) $data['due_date']) : null)
                : $task->due_date,
        ]);

        return response()->json(['data' => $this->transform($task->fresh(['employee', 'assignedByUser']), detailed: true)]);
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(null, 204);
    }

    public function start(Request $request, Task $task): JsonResponse
    {
        $this->authorize('updateStatus', $task);

        try {
            $this->service->start($task, $request->user());
        } catch (TaskException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->transform($task)]);
    }

    public function complete(Request $request, Task $task): JsonResponse
    {
        $this->authorize('updateStatus', $task);

        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']])['remarks'] ?? null;

        try {
            $this->service->complete($task, $request->user(), $remarks);
        } catch (TaskException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->transform($task)]);
    }

    public function cancel(Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        try {
            $this->service->cancel($task);
        } catch (TaskException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->transform($task)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requireTitle = true): array
    {
        return $request->validate([
            'employee_uuid' => [$requireTitle ? 'required' : 'sometimes', 'string', Rule::exists('employees', 'uuid')->where('tenant_id', Tenancy::id())],
            'title' => [$requireTitle ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'due_date' => ['nullable', 'date'],
        ]) + ['priority' => $request->input('priority', 'medium')];
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Task $task, bool $detailed = false): array
    {
        $base = [
            'uuid' => $task->uuid,
            'title' => $task->title,
            'employee' => $task->relationLoaded('employee') ? $task->employee->fullName() : null,
            'priority' => $task->priority->value,
            'status' => $task->status->value,
            'due_date' => $task->due_date?->toDateString(),
            'overdue' => $task->isOverdue(),
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'description' => $task->description,
            'assigned_by' => $task->relationLoaded('assignedByUser') ? $task->assignedByUser?->name : null,
            'started_at' => $task->started_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'remarks' => $task->remarks,
        ];
    }
}
