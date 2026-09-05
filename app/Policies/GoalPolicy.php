<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Goal;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class GoalPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Goal $goal): bool
    {
        if ($user->tenant_id !== $goal->tenant_id) {
            return false;
        }

        return $this->owns($user, $goal)
            || $this->managesSubject($user, $goal)
            || $this->permits($user, $goal->tenant_id, Permission::PerformanceView, Permission::PerformanceManage, Permission::PerformanceReview);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Goal $goal): bool
    {
        if ($user->tenant_id !== $goal->tenant_id) {
            return false;
        }

        return $this->owns($user, $goal)
            || $this->managesSubject($user, $goal)
            || $this->permits($user, $goal->tenant_id, Permission::PerformanceManage);
    }

    /**
     * Attach a manager rating to the goal — a line-manager or performance admin.
     */
    public function rate(User $user, Goal $goal): bool
    {
        if ($user->tenant_id !== $goal->tenant_id) {
            return false;
        }

        return $this->managesSubject($user, $goal)
            || $this->permits($user, $goal->tenant_id, Permission::PerformanceManage, Permission::PerformanceReview);
    }

    public function delete(User $user, Goal $goal): bool
    {
        if ($user->tenant_id !== $goal->tenant_id) {
            return false;
        }

        return $this->owns($user, $goal)
            || $this->permits($user, $goal->tenant_id, Permission::PerformanceManage);
    }

    private function owns(User $user, Goal $goal): bool
    {
        return $goal->employee->user_id === $user->getKey();
    }

    private function managesSubject(User $user, Goal $goal): bool
    {
        return $goal->employee->manager?->user_id === $user->getKey();
    }
}
