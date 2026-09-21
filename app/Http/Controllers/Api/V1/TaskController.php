<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskPriority;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Employee;
use App\Models\Task;
use App\Services\Tasks\TaskService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $canOversee = $user?->can('tasks.view') || $user?->can('tasks.assign') || $user?->can('tasks.manage');

        $tasks = Task::query()
            ->with(['employee:id,uuid,first_name,last_name'])
            ->when(! $canOversee, fn ($q) => $q->where('employee_id', $user?->employee?->getKey()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('employee_uuid'), fn ($q) => $q->whereRelation('employee', 'uuid', $request->string('employee_uuid')->toString()))
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        return TaskResource::collection($tasks);
    }

    public function store(Request $request, TaskService $service): TaskResource
    {
        abort_unless($request->user()?->can('tasks.assign') || $request->user()?->can('tasks.manage'), 403);

        $data = $request->validate([
            'employee_uuid' => ['required', Rule::exists('employees', 'uuid')->where('tenant_id', Tenancy::id())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'due_date' => ['nullable', 'date'],
        ]);

        $employee = Employee::query()->where('uuid', $data['employee_uuid'])->firstOrFail();

        $task = $service->assign(
            $employee,
            $data['title'],
            $data['description'] ?? null,
            TaskPriority::from($data['priority'] ?? 'medium'),
            isset($data['due_date']) ? CarbonImmutable::parse((string) $data['due_date']) : null,
            $request->user(),
        );

        return new TaskResource($task->load('employee'));
    }
}
