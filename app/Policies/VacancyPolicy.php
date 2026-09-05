<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Vacancy;
use App\Policies\Concerns\AuthorizesWithinTenant;

class VacancyPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::RecruitmentView, Permission::RecruitmentManage, Permission::RecruitmentInterview);
    }

    public function view(User $user, Vacancy $vacancy): bool
    {
        return $user->tenant_id === $vacancy->tenant_id
            && $this->permits($user, $vacancy->tenant_id, Permission::RecruitmentView, Permission::RecruitmentManage, Permission::RecruitmentInterview);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::RecruitmentManage);
    }

    public function update(User $user, Vacancy $vacancy): bool
    {
        return $user->tenant_id === $vacancy->tenant_id
            && $this->permits($user, $vacancy->tenant_id, Permission::RecruitmentManage);
    }

    public function delete(User $user, Vacancy $vacancy): bool
    {
        return $this->update($user, $vacancy);
    }
}
