<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\LeaveAccrual;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Support\TenantContext;

/**
 * Seeds a tenant's standard leave types. Tenants edit these or add their own;
 * days/rules are data, not code (no Kenyan labour law hard-coded). Idempotent.
 */
class ProvisionTenantLeaveTypes
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Tenant $tenant): void
    {
        $this->context->run($tenant, function () use ($tenant): void {
            foreach (LeaveType::defaults() as $default) {
                LeaveType::query()->updateOrCreate(
                    ['tenant_id' => $tenant->getKey(), 'code' => $default['code']],
                    [
                        'name' => $default['name'],
                        'default_days_per_year' => $default['days'],
                        'accrual' => $default['code'] === 'ANNUAL' ? LeaveAccrual::Monthly : LeaveAccrual::Annual,
                        'is_paid' => $default['paid'],
                        'requires_approval' => true,
                        'requires_attachment' => $default['attachment'],
                        'allow_carry_forward' => $default['code'] === 'ANNUAL',
                        'max_carry_forward_days' => $default['code'] === 'ANNUAL' ? 10 : null,
                        'gender_restriction' => match ($default['code']) {
                            'MATERNITY' => 'female',
                            'PATERNITY' => 'male',
                            default => null,
                        },
                        'color' => '#'.substr(md5($default['code']), 0, 6),
                        'is_active' => true,
                    ],
                );
            }
        });
    }
}
