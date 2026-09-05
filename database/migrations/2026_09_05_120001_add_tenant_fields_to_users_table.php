<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Nullable + unique so it can be backfilled on an existing install
            // before being tightened; new rows get an ordered UUID from HasUuid.
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->foreignId('tenant_id')->nullable()->after('uuid')
                ->constrained('tenants')->nullOnDelete();
            $table->boolean('is_platform_admin')->default(false)->after('tenant_id');

            $table->index(['tenant_id', 'is_platform_admin']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'is_platform_admin']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['uuid', 'is_platform_admin']);
        });
    }
};
