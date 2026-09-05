<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class BranchPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::EmployeesView, Permission::BranchesManage);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->tenant_id === $branch->tenant_id
            && $this->permits($user, $branch->tenant_id, Permission::EmployeesView, Permission::BranchesManage);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::BranchesManage);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->tenant_id === $branch->tenant_id
            && $this->permits($user, $branch->tenant_id, Permission::BranchesManage);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }
}
