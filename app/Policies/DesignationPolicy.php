<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Designation;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class DesignationPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::EmployeesView, Permission::DesignationsManage);
    }

    public function view(User $user, Designation $designation): bool
    {
        return $user->tenant_id === $designation->tenant_id
            && $this->permits($user, $designation->tenant_id, Permission::EmployeesView, Permission::DesignationsManage);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::DesignationsManage);
    }

    public function update(User $user, Designation $designation): bool
    {
        return $user->tenant_id === $designation->tenant_id
            && $this->permits($user, $designation->tenant_id, Permission::DesignationsManage);
    }

    public function delete(User $user, Designation $designation): bool
    {
        return $this->update($user, $designation);
    }
}
