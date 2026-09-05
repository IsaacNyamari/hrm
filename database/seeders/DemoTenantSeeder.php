<?php

namespace Database\Seeders;

use App\Enums\PaymentProvider;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Plan;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use App\Services\Leave\LeaveService;
use App\Services\Subscription\SubscriptionManager;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoTenantSeeder extends Seeder
{
    /**
     * Two fully isolated demo organizations: ABC on a 14-day trial, XYZ on a
     * paid Business subscription. Used by the tenant-isolation test narrative.
     */
    public function run(): void
    {
        $this->makeTenant('ABC Company', 'abc-company', 'owner@abc-company.test', 'starter', 'trial');
        $this->makeTenant('XYZ Limited', 'xyz-limited', 'owner@xyz-limited.test', 'business', 'active');
    }

    private function makeTenant(string $name, string $slug, string $ownerEmail, string $planSlug, string $mode): void
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
                'status' => TenantStatus::Pending,
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

        $plan = Plan::query()->where('slug', $planSlug)->firstOrFail();
        $subscriptions = app(SubscriptionManager::class);

        if ($tenant->subscription()->doesntExist()) {
            if ($mode === 'trial') {
                $subscriptions->startTrial($tenant, $plan);
            } else {
                [$subscription, $invoice, $payment] = $subscriptions->subscribe($tenant, $plan, PaymentProvider::Manual);

                if ($invoice !== null && $payment !== null) {
                    app(InvoiceService::class)->markPaid($invoice, $payment);
                    $subscriptions->activatePaidInvoice($invoice);
                }
            }
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant, $owner, $slug, $name): void {
            $owner->syncRoles([Role::TenantOwner->value]);

            if ($tenant->employees()->count() > 0) {
                return;
            }

            $hq = Branch::factory()->forTenant($tenant)->headquarters()->create();
            $departments = Department::factory()->forTenant($tenant)->count(4)->create();
            $designations = Designation::factory()->forTenant($tenant)->count(4)->create();
            $permanent = EmploymentType::query()->where('slug', 'permanent')->first();

            // Nest the last two departments under the first.
            $departments->slice(2)->each(fn (Department $d) => $d->update(['parent_id' => $departments->first()->id]));

            $manager = Employee::factory()->forTenant($tenant)->create([
                'user_id' => $owner->getKey(),
                'employee_number' => strtoupper(substr($slug, 0, 3)).'-EMP-000001',
                'first_name' => $name,
                'last_name' => 'Owner',
                'email' => 'owner@'.$slug.'.test',
                'job_title' => 'Founder',
                'branch_id' => $hq->id,
                'department_id' => $departments->first()->id,
                'employment_type_id' => $permanent?->id,
            ]);

            $departments->first()->update(['head_employee_id' => $manager->id]);

            Employee::factory()->forTenant($tenant)->count(8)->create([
                'branch_id' => $hq->id,
                'manager_id' => $manager->id,
                'department_id' => $departments->random()->id,
                'designation_id' => $designations->random()->id,
                'employment_type_id' => $permanent?->id,
            ]);

            $shift = Shift::factory()->forTenant($tenant)->default()->create();

            Employee::query()->each(function (Employee $employee) use ($tenant, $shift): void {
                $employee->shiftAssignments()->create([
                    'tenant_id' => $tenant->id,
                    'shift_id' => $shift->id,
                    'effective_from' => now()->subMonths(3)->toDateString(),
                ]);
            });

            app(LeaveService::class)->accrue();
        });
    }
}
