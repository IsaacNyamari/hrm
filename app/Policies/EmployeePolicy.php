<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * Baseline tenant-ownership authorization. Independent of the query-level
 * TenantScope — this is the second, deliberate line of defence.
 *
 * Platform admins are intentionally denied direct access to tenant HR data;
 * they reach it only through audited impersonation (P7).
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->sharesTenant($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->sharesTenant($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->sharesTenant($user, $employee);
    }

    public function restore(User $user, Employee $employee): bool
    {
        return $this->sharesTenant($user, $employee);
    }

    private function sharesTenant(User $user, Employee $employee): bool
    {
        return $user->tenant_id !== null
            && $user->tenant_id === $employee->tenant_id;
    }
}
