<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\HrRequest;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class HrRequestPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, HrRequest $request): bool
    {
        return $user->tenant_id === $request->tenant_id
            && ($this->owns($user, $request) || $this->isHr($user, $request->tenant_id) || $request->assigned_to === $user->getKey());
    }

    public function create(User $user): bool
    {
        return $user->employee !== null;
    }

    /**
     * Assign, change status, resolve, add internal comments.
     */
    public function manage(User $user, HrRequest $request): bool
    {
        return $user->tenant_id === $request->tenant_id
            && ($this->isHr($user, $request->tenant_id) || $request->assigned_to === $user->getKey());
    }

    public function comment(User $user, HrRequest $request): bool
    {
        return $this->view($user, $request);
    }

    private function owns(User $user, HrRequest $request): bool
    {
        return $user->employee?->getKey() === $request->employee_id;
    }

    private function isHr(User $user, int $tenantId): bool
    {
        return $this->permits($user, $tenantId, Permission::EmployeesView, Permission::UsersManage);
    }
}
