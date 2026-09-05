<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('reference');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('currency', 3)->default('KES');
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->string('status')->default('draft');
            $table->unsignedSmallInteger('current_level')->default(1);
            $table->date('incurred_on')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamp('reimbursed_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'employee_id', 'status']);
        });

        Schema::create('expense_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->string('description');
            $table->string('category')->default('other');
            $table->date('spent_on');
            $table->unsignedBigInteger('amount_cents');
            $table->decimal('quantity', 10, 2)->nullable();       // km for mileage
            $table->unsignedBigInteger('unit_rate_cents')->nullable(); // mileage rate applied
            $table->string('receipt_disk')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'expense_id']);
        });

        Schema::create('expense_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->string('approver_type'); // manager | finance
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['expense_id', 'level']);
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('asset_tag');
            $table->string('name');
            $table->string('category')->default('other');
            $table->string('serial_number')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('available');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('purchased_on')->nullable();
            $table->unsignedBigInteger('purchase_cost_cents')->nullable();
            $table->string('currency', 3)->default('KES');
            $table->date('warranty_expires_on')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'asset_tag']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('asset_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('assigned_on');
            $table->date('due_back_on')->nullable();
            $table->date('returned_on')->nullable();
            $table->string('status')->default('active');
            $table->string('condition_out')->nullable();
            $table->string('condition_in')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'asset_id', 'status']);
            $table->index(['tenant_id', 'employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('expense_approvals');
        Schema::dropIfExists('expense_items');
        Schema::dropIfExists('expenses');
    }
};
