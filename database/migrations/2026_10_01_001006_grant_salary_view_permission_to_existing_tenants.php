<?php

declare(strict_types=1);

use App\Actions\Tenancy\ProvisionTenantRoles;
use App\Models\Tenant;
use App\Support\Auditor;
use Illuminate\Database\Migrations\Migration;

/**
 * Pay is now gated by its own employees.salary.view permission instead of
 * being visible to everyone who can view an employee. Re-running the
 * idempotent role provisioning creates the permission and grants it to the
 * roles that handle pay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Auditor::muted(function (): void {
            Tenant::query()->chunkById(100, function ($tenants): void {
                foreach ($tenants as $tenant) {
                    app(ProvisionTenantRoles::class)->handle($tenant);
                }
            });
        });
    }

    public function down(): void
    {
        // The permission is harmless if left in place.
    }
};
