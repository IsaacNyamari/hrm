<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Interview;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class InterviewPolicy
{
    use AuthorizesWithinTenant;

    public function view(User $user, Interview $interview): bool
    {
        if ($user->tenant_id !== $interview->tenant_id) {
            return false;
        }

        return $interview->interviewer_id === $user->getKey()
            || $this->permits($user, $interview->tenant_id, Permission::RecruitmentView, Permission::RecruitmentManage);
    }

    /**
     * Record a verdict / feedback for the interview.
     */
    public function submitFeedback(User $user, Interview $interview): bool
    {
        if ($user->tenant_id !== $interview->tenant_id) {
            return false;
        }

        return $interview->interviewer_id === $user->getKey()
            || $this->permits($user, $interview->tenant_id, Permission::RecruitmentManage);
    }

    public function update(User $user, Interview $interview): bool
    {
        return $user->tenant_id === $interview->tenant_id
            && $this->permits($user, $interview->tenant_id, Permission::RecruitmentManage);
    }
}
