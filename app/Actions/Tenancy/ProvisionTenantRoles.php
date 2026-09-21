<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\Tenant;
use App\Support\TenantContext;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates the standard set of tenant roles (scoped to the given tenant via
 * Spatie teams) with their default permissions. Idempotent — safe to re-run
 * after the permission catalogue changes: permissions added to the enum since
 * the catalogue was last seeded are created before roles are synced.
 */
class ProvisionTenantRoles
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Tenant $tenant): void
    {
        $this->ensurePermissionsExist();

        $this->context->run($tenant, function () use ($tenant): void {
            foreach (RoleEnum::tenantRoles() as $roleEnum) {
                $role = Role::findOrCreate($roleEnum->value, 'web');

                // Guard against a role row leaking in without the team key.
                $role->forceFill(['tenant_id' => $tenant->getKey()])->save();

                $role->syncPermissions($roleEnum->permissions());
            }
        });
    }

    private function ensurePermissionsExist(): void
    {
        $existing = Permission::query()->where('guard_name', 'web')->pluck('name')->all();

        foreach (array_diff(PermissionEnum::values(), $existing) as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }
}
