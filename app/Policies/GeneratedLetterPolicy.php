<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\GeneratedLetter;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class GeneratedLetterPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, GeneratedLetter $letter): bool
    {
        if ($user->tenant_id !== $letter->tenant_id) {
            return false;
        }

        return $this->permits($user, $letter->tenant_id, Permission::EmployeesDocumentsView)
            || $user->employee?->getKey() === $letter->employee_id;
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::EmployeesDocumentsUpload);
    }
}
