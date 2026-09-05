<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DocumentType;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class DocumentTypePolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, DocumentType $type): bool
    {
        return $user->tenant_id === $type->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::EmployeesDocumentsUpload);
    }

    public function update(User $user, DocumentType $type): bool
    {
        return $user->tenant_id === $type->tenant_id
            && $this->permits($user, $type->tenant_id, Permission::EmployeesDocumentsUpload);
    }

    public function delete(User $user, DocumentType $type): bool
    {
        return $this->update($user, $type);
    }
}
