<?php

declare(strict_types=1);

use App\Actions\Tenancy\ProvisionTenantDocuments;
use App\Models\Tenant;
use App\Support\Auditor;
use Illuminate\Database\Migrations\Migration;

/**
 * Existing organizations only had the two starter letters. The provisioning
 * action is idempotent (matched by template name), so re-running it adds the
 * offer, warning, termination, certificate of service and salary certificate
 * templates without touching anything already customised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Auditor::muted(function (): void {
            Tenant::query()->chunkById(100, function ($tenants): void {
                foreach ($tenants as $tenant) {
                    app(ProvisionTenantDocuments::class)->handle($tenant);
                }
            });
        });
    }

    public function down(): void
    {
        // Templates may have been edited or used; leave them in place.
    }
};
