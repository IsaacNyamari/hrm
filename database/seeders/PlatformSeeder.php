<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'superadmin@hrm.test'],
            [
                'name' => 'Platform Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'tenant_id' => null,
                'is_platform_admin' => true,
                'platform_role' => Role::SuperAdmin->value,
            ],
        );
    }
}
