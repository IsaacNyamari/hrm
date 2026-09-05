<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'superadmin@hrm.test'],
            [
                'name' => 'Platform Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'tenant_id' => null,
                'is_platform_admin' => true,
            ],
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId(TenantContext::PLATFORM_TEAM_ID);
        $admin->syncRoles([Role::SuperAdmin->value]);
    }
}
