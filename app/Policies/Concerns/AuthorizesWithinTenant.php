<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\Permission;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Evaluates a user's permissions inside a specific tenant's team scope,
 * regardless of the team currently bound to the request. This makes policies
 * correct even when called outside the normal middleware pipeline (console,
 * queued jobs, direct Gate assertions in tests).
 */
trait AuthorizesWithinTenant
{
    protected function permits(User $user, ?int $tenantId, Permission ...$permissions): bool
    {
        if ($tenantId === null) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenantId);

        try {
            foreach ($permissions as $permission) {
                if ($user->hasPermissionTo($permission->value)) {
                    return true;
                }
            }

            return false;
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
