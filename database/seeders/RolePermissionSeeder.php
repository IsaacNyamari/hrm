<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\PlatformPermission;
use App\Enums\Role as RoleEnum;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the global permission catalogue (tenant-side + platform-side) and the
 * platform roles. Tenant roles are created per tenant by
 * App\Actions\Tenancy\ProvisionTenantRoles. One authorization system — Spatie —
 * for both platform and tenant, split by "team" (tenant_id, with 0 = platform).
 *
 * Uses raw upserts: this runs before every feature test.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $tables = config('permission.table_names');
        $team = TenantContext::PLATFORM_TEAM_ID;

        // 1. Permissions (no team column).
        $names = [...PermissionEnum::values(), ...PlatformPermission::values()];
        DB::table($tables['permissions'])->upsert(
            array_map(static fn (string $n): array => ['name' => $n, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now], $names),
            ['name', 'guard_name'],
            ['updated_at'],
        );

        // 2. Platform roles under team 0.
        DB::table($tables['roles'])->upsert(
            array_map(static fn (RoleEnum $r): array => [
                'name' => $r->value, 'guard_name' => 'web', 'tenant_id' => $team, 'created_at' => $now, 'updated_at' => $now,
            ], RoleEnum::platformRoles()),
            ['tenant_id', 'name', 'guard_name'],
            ['updated_at'],
        );

        // 3. role_has_permissions for the platform roles.
        $permissionIds = DB::table($tables['permissions'])->pluck('id', 'name');
        $roleIds = DB::table($tables['roles'])->where('tenant_id', $team)->pluck('id', 'name');

        $pivot = [];
        foreach (RoleEnum::platformRoles() as $roleEnum) {
            foreach ($roleEnum->platformPermissions() as $permissionName) {
                if (isset($roleIds[$roleEnum->value], $permissionIds[$permissionName])) {
                    $pivot[] = [
                        'role_id' => $roleIds[$roleEnum->value],
                        'permission_id' => $permissionIds[$permissionName],
                    ];
                }
            }
        }

        if ($pivot !== []) {
            DB::table($tables['role_has_permissions'])->upsert($pivot, ['permission_id', 'role_id'], ['role_id']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
