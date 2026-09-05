<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\EmploymentType;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/**
 * Seeds the standard employment types for a tenant. Tenants may add their own
 * or deactivate these afterwards. Idempotent.
 */
class ProvisionTenantEmploymentTypes
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Tenant $tenant): void
    {
        $this->context->run($tenant, function () use ($tenant): void {
            foreach (EmploymentType::defaults() as $name) {
                EmploymentType::query()->updateOrCreate(
                    ['tenant_id' => $tenant->getKey(), 'slug' => Str::slug($name)],
                    ['name' => $name, 'is_system' => true, 'is_active' => true],
                );
            }
        });
    }
}
