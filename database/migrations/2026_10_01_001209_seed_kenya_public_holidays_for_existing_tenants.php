<?php

declare(strict_types=1);

use App\Actions\Tenancy\ProvisionTenantHolidays;
use App\Models\Tenant;
use App\Support\Auditor;
use Illuminate\Database\Migrations\Migration;

/**
 * Existing organizations had no public-holiday calendar, so leave requests
 * spanning a holiday were charged for it. Seed the fixed-date Kenyan public
 * holidays (plus Good Friday and Easter Monday) for this and next year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Auditor::muted(function (): void {
            Tenant::query()->chunkById(100, function ($tenants): void {
                foreach ($tenants as $tenant) {
                    app(ProvisionTenantHolidays::class)->handle($tenant);
                }
            });
        });
    }

    public function down(): void
    {
        // Holidays may have been edited by the organization; leave them in place.
    }
};
