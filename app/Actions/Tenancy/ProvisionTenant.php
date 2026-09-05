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
        private readonly ProvisionTenantLeaveTypes $leaveTypes,
        private readonly ProvisionTenantPayroll $payroll,
    ) {}

    public function handle(Tenant $tenant): void
    {
        $this->roles->handle($tenant);
        $this->employmentTypes->handle($tenant);
        $this->leaveTypes->handle($tenant);
        $this->payroll->handle($tenant);
    }
}
