<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class TaskPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id
            && ($this->owns($user, $task) || $this->canOversee($user, $task->tenant_id));
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::TasksAssign, Permission::TasksManage);
    }

    /**
     * Edit details, reassign, or cancel a task.
     */
    public function update(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id
            && $this->permits($user, $task->tenant_id, Permission::TasksManage);
    }

    /**
     * Start or complete a task — the assignee themselves, or anyone who can
     * manage tasks.
     */
    public function updateStatus(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id
            && ($this->owns($user, $task) || $this->permits($user, $task->tenant_id, Permission::TasksManage));
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id
            && $this->permits($user, $task->tenant_id, Permission::TasksManage);
    }

    private function owns(User $user, Task $task): bool
    {
        return $task->employee->user_id === $user->getKey();
    }

    private function canOversee(User $user, int $tenantId): bool
    {
        return $this->permits($user, $tenantId, Permission::TasksView, Permission::TasksAssign, Permission::TasksManage);
    }
}
