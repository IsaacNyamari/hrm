<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PerformanceCycle;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class PerformanceCyclePolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, PerformanceCycle $cycle): bool
    {
        return $user->tenant_id === $cycle->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::PerformanceManage);
    }

    public function update(User $user, PerformanceCycle $cycle): bool
    {
        return $user->tenant_id === $cycle->tenant_id
            && $this->permits($user, $cycle->tenant_id, Permission::PerformanceManage);
    }

    public function delete(User $user, PerformanceCycle $cycle): bool
    {
        return $this->update($user, $cycle);
    }
}
