<?php

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoTenantSeeder extends Seeder
{
    /**
     * Two fully isolated demo organizations, each with an owner login and a
     * handful of employees. Used by the tenant-isolation test narrative.
     */
    public function run(): void
    {
        $this->makeTenant('ABC Company', 'abc-company', 'owner@abc-company.test');
        $this->makeTenant('XYZ Limited', 'xyz-limited', 'owner@xyz-limited.test');
    }

    private function makeTenant(string $name, string $slug, string $ownerEmail): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'legal_name' => $name.' Ltd',
                'email' => 'hr@'.$slug.'.test',
                'country' => 'KE',
                'currency' => 'KES',
                'timezone' => 'Africa/Nairobi',
                'status' => TenantStatus::Active,
            ],
        );

        $owner = User::query()->firstOrCreate(
            ['email' => $ownerEmail],
            [
                'name' => $name.' Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'tenant_id' => $tenant->getKey(),
                'is_platform_admin' => false,
            ],
        );

        if ($tenant->employees()->count() === 0) {
            Employee::factory()->count(6)->forTenant($tenant)->create();

            Employee::factory()->forTenant($tenant)->create([
                'user_id' => $owner->getKey(),
                'employee_number' => strtoupper(substr($slug, 0, 3)).'-EMP-000001',
                'first_name' => $name,
                'last_name' => 'Owner',
                'email' => $ownerEmail,
                'job_title' => 'Founder',
            ]);
        }
    }
}
