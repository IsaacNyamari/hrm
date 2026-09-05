<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class EmployeeDocumentPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, EmployeeDocument $document): bool
    {
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        if ($this->permits($user, $document->tenant_id, Permission::EmployeesDocumentsView)) {
            return true;
        }

        return ! $document->is_confidential && $user->employee?->getKey() === $document->employee_id;
    }

    public function create(User $user): bool
    {
        return $user->employee !== null
            || $this->permits($user, $user->tenant_id, Permission::EmployeesDocumentsUpload);
    }

    public function delete(User $user, EmployeeDocument $document): bool
    {
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        if ($this->permits($user, $document->tenant_id, Permission::EmployeesDocumentsUpload)) {
            return true;
        }

        return ! $document->is_confidential
            && $document->uploaded_by === $user->getKey()
            && $user->employee?->getKey() === $document->employee_id;
    }
}
