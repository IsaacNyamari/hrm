<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class LeaveRequestPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, LeaveRequest $request): bool
    {
        if ($user->tenant_id !== $request->tenant_id) {
            return false;
        }

        return $this->owns($user, $request) || $this->canOversee($user, $request);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::LeaveRequest, Permission::LeaveManage);
    }

    public function decide(User $user, LeaveRequest $request): bool
    {
        if ($user->tenant_id !== $request->tenant_id) {
            return false;
        }

        if ($this->permits($user, $request->tenant_id, Permission::LeaveApprove, Permission::LeaveManage)) {
            return true;
        }

        // A line manager can decide on their report's request.
        return $request->employee->manager?->user_id === $user->getKey();
    }

    public function cancel(User $user, LeaveRequest $request): bool
    {
        if ($user->tenant_id !== $request->tenant_id) {
            return false;
        }

        return $this->owns($user, $request)
            || $this->permits($user, $request->tenant_id, Permission::LeaveApprove, Permission::LeaveManage);
    }

    private function owns(User $user, LeaveRequest $request): bool
    {
        return $request->requested_by === $user->getKey()
            || $request->employee->user_id === $user->getKey();
    }

    private function canOversee(User $user, LeaveRequest $request): bool
    {
        return $this->permits($user, $request->tenant_id, Permission::LeaveView, Permission::LeaveApprove, Permission::LeaveManage)
            || $request->employee->manager?->user_id === $user->getKey();
    }
}
