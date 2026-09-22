<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Enums\TaskStatus;
use App\Exceptions\TaskException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Employee;
use App\Models\Task;
use App\Services\Tasks\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Tasks assigned to the token owner. `?status=open` is pending + in progress
 * (soonest due first); a single status narrows further. Starting and
 * completing go through TaskService, as on the task board.
 */
class TaskController extends Controller
{
    public function __construct(private readonly TaskService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $employee = $this->employee($request);

        $status = $request->validate([
            'status' => ['nullable', Rule::in(['open', ...array_map(fn (TaskStatus $s): string => $s->value, TaskStatus::cases())])],
        ])['status'] ?? null;

        $open = [TaskStatus::Pending->value, TaskStatus::InProgress->value];

        $tasks = Task::query()
            ->where('employee_id', $employee->getKey())
            ->when($status === 'open', fn ($q) => $q->whereIn('status', $open))
            ->when($status !== null && $status !== 'open', fn ($q) => $q->where('status', $status))
            ->when(
                in_array($status, ['open', TaskStatus::Pending->value, TaskStatus::InProgress->value], true),
                fn ($q) => $q->orderByRaw('due_date is null')->orderBy('due_date')->orderByDesc('id'),
                fn ($q) => $q->orderByDesc('completed_at')->orderByDesc('id'),
            )
            ->paginate(min($request->integer('per_page', 30), 50));

        return TaskResource::collection($tasks);
    }

    public function start(Request $request, string $task): JsonResponse|TaskResource
    {
        $record = $this->find($request, $task);

        try {
            $this->service->start($record, $request->user());
        } catch (TaskException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new TaskResource($record->refresh());
    }

    public function complete(Request $request, string $task): JsonResponse|TaskResource
    {
        $record = $this->find($request, $task);
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']])['remarks'] ?? null;

        try {
            $this->service->complete($record, $request->user(), $remarks);
        } catch (TaskException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new TaskResource($record->refresh());
    }

    private function find(Request $request, string $uuid): Task
    {
        $employee = $this->employee($request);

        $task = Task::query()
            ->where('uuid', $uuid)
            ->where('employee_id', $employee->getKey())
            ->firstOrFail();

        $this->authorize('updateStatus', $task);

        return $task;
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user()?->employee;
        abort_if($employee === null, 422, 'Your account is not linked to an employee record.');

        return $employee;
    }
}
