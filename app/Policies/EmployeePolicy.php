<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

/**
 * Two independent gates must both pass:
 *  1. the user and the record belong to the same tenant, and
 *  2. the user holds the relevant permission within that tenant's role scope.
 *
 * The tenant check is deliberate defence in depth — it does not rely on the
 * query-level TenantScope having been applied.
 *
 * Platform admins are intentionally denied direct access to tenant HR data;
 * they reach it only through audited impersonation (P7).
 */
class EmployeePolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::EmployeesView);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->sharesTenant($user, $employee)
            && $this->permits($user, $employee->tenant_id, Permission::EmployeesView);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::EmployeesCreate);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->sharesTenant($user, $employee)
            && $this->permits($user, $employee->tenant_id, Permission::EmployeesUpdate);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->sharesTenant($user, $employee)
            && $this->permits($user, $employee->tenant_id, Permission::EmployeesDelete);
    }

    public function restore(User $user, Employee $employee): bool
    {
        return $this->sharesTenant($user, $employee)
            && $this->permits($user, $employee->tenant_id, Permission::EmployeesDelete);
    }

    private function sharesTenant(User $user, Employee $employee): bool
    {
        return $user->tenant_id !== null
            && $user->tenant_id === $employee->tenant_id;
    }
}
