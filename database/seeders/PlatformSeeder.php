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
            ['email' => config('platform.admin.email')],
            [
                'name' => config('platform.admin.name'),
                'password' => Hash::make((string) config('platform.admin.password')),
                'email_verified_at' => now(),
                'tenant_id' => null,
                'is_platform_admin' => true,
            ],
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId(TenantContext::PLATFORM_TEAM_ID);
        $admin->syncRoles([Role::SuperAdmin->value]);
    }
}
