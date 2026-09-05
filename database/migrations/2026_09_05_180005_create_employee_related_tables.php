<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_emergency_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone');
            $table->string('alternate_phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
        });

        Schema::create('employee_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('bank_name');
            $table->string('branch_name')->nullable();
            $table->string('account_name');
            $table->text('account_number');            // encrypted at rest
            $table->string('swift_code')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
        });

        Schema::create('employee_statutory_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->text('tax_pin')->nullable();       // KRA PIN — encrypted
            $table->text('nssf_number')->nullable();   // encrypted
            $table->text('shif_number')->nullable();   // encrypted
            $table->text('nhif_number')->nullable();   // encrypted (legacy)
            $table->text('pension_number')->nullable();
            $table->text('helb_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_statutory_details');
        Schema::dropIfExists('employee_bank_accounts');
        Schema::dropIfExists('employee_emergency_contacts');
    }
};
