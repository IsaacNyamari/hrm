<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code');
            $table->string('color', 9)->nullable();
            $table->decimal('default_days_per_year', 5, 2)->default(0);
            $table->string('accrual')->default('none'); // none | monthly | annual
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->unsignedSmallInteger('min_notice_days')->default(0);
            $table->unsignedSmallInteger('max_consecutive_days')->nullable();
            $table->boolean('allow_carry_forward')->default(false);
            $table->decimal('max_carry_forward_days', 5, 2)->nullable();
            $table->string('gender_restriction')->nullable();          // male | female | null
            $table->foreignId('employment_type_id')->nullable()->constrained('employment_types')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
