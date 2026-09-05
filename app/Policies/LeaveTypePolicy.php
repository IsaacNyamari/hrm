<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\LeaveType;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class LeaveTypePolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::LeaveView, Permission::LeaveRequest, Permission::LeaveManage);
    }

    public function view(User $user, LeaveType $leaveType): bool
    {
        return $user->tenant_id === $leaveType->tenant_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::LeaveManage);
    }

    public function update(User $user, LeaveType $leaveType): bool
    {
        return $user->tenant_id === $leaveType->tenant_id
            && $this->permits($user, $leaveType->tenant_id, Permission::LeaveManage);
    }

    public function delete(User $user, LeaveType $leaveType): bool
    {
        return $this->update($user, $leaveType);
    }
}
