<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Application;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class ApplicationPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::RecruitmentView, Permission::RecruitmentManage, Permission::RecruitmentInterview);
    }

    public function view(User $user, Application $application): bool
    {
        if ($user->tenant_id !== $application->tenant_id) {
            return false;
        }

        if ($this->permits($user, $application->tenant_id, Permission::RecruitmentView, Permission::RecruitmentManage)) {
            return true;
        }

        if ($application->assigned_to === $user->getKey()) {
            return true;
        }

        // An interviewer on this application can see it.
        return $application->interviews()->where('interviewer_id', $user->getKey())->exists();
    }

    public function manage(User $user, Application $application): bool
    {
        return $user->tenant_id === $application->tenant_id
            && $this->permits($user, $application->tenant_id, Permission::RecruitmentManage);
    }
}
