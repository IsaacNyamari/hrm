<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\JobPosition;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class JobPositionPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::RecruitmentView, Permission::RecruitmentManage);
    }

    public function view(User $user, JobPosition $position): bool
    {
        return $user->tenant_id === $position->tenant_id
            && $this->permits($user, $position->tenant_id, Permission::RecruitmentView, Permission::RecruitmentManage);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::RecruitmentManage);
    }

    public function update(User $user, JobPosition $position): bool
    {
        return $user->tenant_id === $position->tenant_id
            && $this->permits($user, $position->tenant_id, Permission::RecruitmentManage);
    }

    public function delete(User $user, JobPosition $position): bool
    {
        return $this->update($user, $position);
    }
}
