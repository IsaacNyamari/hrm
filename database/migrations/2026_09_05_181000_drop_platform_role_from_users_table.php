<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-side roles now live in Spatie under the reserved team id 0
 * (App\Support\TenantContext::PLATFORM_TEAM_ID) — one authorization system for
 * both platform and tenant. The dedicated column is no longer needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'platform_role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('platform_role');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'platform_role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('platform_role')->nullable()->after('is_platform_admin');
            });
        }
    }
};
