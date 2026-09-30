<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

/**
 * Governs a tenant admin managing role assignment for people within their own
 * tenant. Granting or changing the Tenant Owner role itself is reserved to
 * the platform admin (see App\Livewire\Platform\TenantDetail) and is refused
 * here regardless of this policy's answer.
 */
class UserPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::RolesManage);
    }

    public function manageRoles(User $user, User $target): bool
    {
        return $this->sharesTenant($user, $target)
            && $this->permits($user, $target->tenant_id, Permission::RolesManage);
    }

    private function sharesTenant(User $user, User $target): bool
    {
        return $user->tenant_id !== null
            && $user->tenant_id === $target->tenant_id;
    }
}
