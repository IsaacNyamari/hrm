<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Applicant;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class ApplicantPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::RecruitmentView, Permission::RecruitmentManage);
    }

    public function view(User $user, Applicant $applicant): bool
    {
        return $user->tenant_id === $applicant->tenant_id
            && $this->permits($user, $applicant->tenant_id, Permission::RecruitmentView, Permission::RecruitmentManage, Permission::RecruitmentInterview);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::RecruitmentManage);
    }

    public function update(User $user, Applicant $applicant): bool
    {
        return $user->tenant_id === $applicant->tenant_id
            && $this->permits($user, $applicant->tenant_id, Permission::RecruitmentManage);
    }
}
