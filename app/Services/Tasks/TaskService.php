<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\Permission;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Events\TaskAssigned;
use App\Events\TaskCompleted;
use App\Exceptions\TaskException;
use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Permission\PermissionRegistrar;

class TaskService
{
    /**
     * Assign a new task to an employee.
     */
    public function assign(
        Employee $employee,
        string $title,
        ?string $description,
        TaskPriority $priority,
        ?CarbonImmutable $dueDate,
        ?User $assignedBy = null,
    ): Task {
        $task = Task::query()->create([
            'employee_id' => $employee->getKey(),
            'assigned_by' => $assignedBy?->getKey(),
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'status' => TaskStatus::Pending,
            'due_date' => $dueDate?->toDateString(),
        ]);

        TaskAssigned::dispatch($task);

        return $task;
    }

    /**
     * Update a task's details (not its status or assignee).
     *
     * @param  array{title?: string, description?: ?string, priority?: TaskPriority, due_date?: ?CarbonImmutable}  $attributes
     */
    public function update(Task $task, array $attributes): Task
    {
        $task->forceFill([
            'title' => $attributes['title'] ?? $task->title,
            'description' => $attributes['description'] ?? $task->description,
            'priority' => $attributes['priority'] ?? $task->priority,
            'due_date' => array_key_exists('due_date', $attributes)
                ? $attributes['due_date']?->toDateString()
                : $task->due_date,
        ])->save();

        return $task;
    }

    /**
     * Reassign an open task to a different employee, resetting its progress —
     * the new assignee starts from a clean pending state.
     */
    public function reassign(Task $task, Employee $employee): Task
    {
        if ($task->status->isTerminal()) {
            throw TaskException::alreadyFinished();
        }

        $task->forceFill([
            'employee_id' => $employee->getKey(),
            'status' => TaskStatus::Pending,
            'started_at' => null,
        ])->save();

        TaskAssigned::dispatch($task);

        return $task;
    }

    /**
     * Mark a task in progress. Only the assignee or someone who can manage
     * tasks may do this.
     */
    public function start(Task $task, User $actor): Task
    {
        $this->guardActor($task, $actor);

        if ($task->status !== TaskStatus::Pending) {
            throw TaskException::notPending();
        }

        $task->forceFill(['status' => TaskStatus::InProgress, 'started_at' => CarbonImmutable::now()])->save();

        return $task;
    }

    /**
     * Mark a task complete. Only the assignee or someone who can manage tasks
     * may do this.
     */
    public function complete(Task $task, User $actor, ?string $remarks = null): Task
    {
        $this->guardActor($task, $actor);

        if ($task->status->isTerminal()) {
            throw TaskException::alreadyFinished();
        }

        $task->forceFill([
            'status' => TaskStatus::Completed,
            'completed_at' => CarbonImmutable::now(),
            'remarks' => $remarks,
        ])->save();

        TaskCompleted::dispatch($task);

        return $task;
    }

    public function cancel(Task $task): Task
    {
        if ($task->status->isTerminal()) {
            throw TaskException::alreadyFinished();
        }

        $task->forceFill(['status' => TaskStatus::Cancelled])->save();

        return $task;
    }

    private function guardActor(Task $task, User $actor): void
    {
        $isAssignee = $task->employee->user_id === $actor->getKey();

        if ($isAssignee || $this->userCan($actor, $task->tenant_id, Permission::TasksManage)) {
            return;
        }

        throw TaskException::notAssignee();
    }

    private function userCan(User $user, ?int $tenantId, Permission ...$permissions): bool
    {
        if ($tenantId === null) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenantId);

        try {
            foreach ($permissions as $permission) {
                if ($user->hasPermissionTo($permission->value)) {
                    return true;
                }
            }

            return false;
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
