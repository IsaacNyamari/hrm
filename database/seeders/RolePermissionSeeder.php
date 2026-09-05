<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the global tenant-side permission catalogue. Tenant roles are created
 * per tenant by App\Actions\Tenancy\ProvisionTenantRoles; platform-side
 * authorization uses the users.platform_role column, not Spatie roles.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = array_map(static fn (string $name): array => [
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ], PermissionEnum::values());

        DB::table(config('permission.table_names.permissions', 'permissions'))
            ->upsert($rows, ['name', 'guard_name'], ['updated_at']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
