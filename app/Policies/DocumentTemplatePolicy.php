<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class DocumentTemplatePolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::EmployeesDocumentsView, Permission::EmployeesDocumentsUpload);
    }

    public function view(User $user, DocumentTemplate $template): bool
    {
        return $user->tenant_id === $template->tenant_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::EmployeesDocumentsUpload);
    }

    public function update(User $user, DocumentTemplate $template): bool
    {
        return $user->tenant_id === $template->tenant_id
            && $this->permits($user, $template->tenant_id, Permission::EmployeesDocumentsUpload);
    }

    public function delete(User $user, DocumentTemplate $template): bool
    {
        return $this->update($user, $template);
    }
}
