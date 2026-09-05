<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Tenant;

/**
 * One-stop tenant bootstrap: roles/permissions + reference data. Called on
 * trial start and first subscribe; safe to re-run.
 */
class ProvisionTenant
{
    public function __construct(
        private readonly ProvisionTenantRoles $roles,
        private readonly ProvisionTenantEmploymentTypes $employmentTypes,
    ) {}

    public function handle(Tenant $tenant): void
    {
        $this->roles->handle($tenant);
        $this->employmentTypes->handle($tenant);
    }
}
