<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class DepartmentPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::EmployeesView, Permission::DepartmentsManage);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->tenant_id === $department->tenant_id
            && $this->permits($user, $department->tenant_id, Permission::EmployeesView, Permission::DepartmentsManage);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::DepartmentsManage);
    }

    public function update(User $user, Department $department): bool
    {
        return $user->tenant_id === $department->tenant_id
            && $this->permits($user, $department->tenant_id, Permission::DepartmentsManage);
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->update($user, $department);
    }
}
