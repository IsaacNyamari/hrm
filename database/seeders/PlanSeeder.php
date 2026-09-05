<?php

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Reference plans. Prices/limits are data, not application logic — a platform
 * admin can edit or add plans without a deploy.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Core HR for small teams.',
                'price_cents' => 250000, // KES 2,500
                'employee_limit' => 20,
                'storage_limit_mb' => 1024,
                'trial_days' => 14,
                'sort_order' => 1,
                'features' => ['flags' => ['hr_core', 'leave', 'attendance', 'reports'], 'limits' => ['branches' => 1]],
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Payroll, documents and multi-branch HR.',
                'price_cents' => 500000, // KES 5,000
                'employee_limit' => 100,
                'storage_limit_mb' => 10240,
                'trial_days' => 14,
                'sort_order' => 2,
                'features' => ['flags' => ['hr_core', 'leave', 'attendance', 'reports', 'payroll', 'documents'], 'limits' => ['branches' => 10]],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Unlimited scale with API access and integrations.',
                'price_cents' => 1000000, // KES 10,000
                'employee_limit' => null,
                'storage_limit_mb' => null,
                'trial_days' => 14,
                'sort_order' => 3,
                'features' => ['flags' => ['hr_core', 'leave', 'attendance', 'reports', 'payroll', 'documents', 'performance', 'recruitment', 'expenses', 'assets', 'api_access'], 'limits' => []],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                $plan + ['currency' => 'KES', 'billing_interval' => BillingInterval::Monthly, 'is_active' => true],
            );
        }
    }
}
