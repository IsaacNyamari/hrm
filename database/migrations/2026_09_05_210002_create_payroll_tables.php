<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('frequency')->default('monthly');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique(['tenant_id', 'start_date', 'end_date']);
        });

        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedInteger('employee_count')->default(0);
            $table->unsignedBigInteger('gross_cents')->default(0);
            $table->unsignedBigInteger('taxable_cents')->default(0);
            $table->unsignedBigInteger('statutory_cents')->default(0);
            $table->unsignedBigInteger('deductions_cents')->default(0);
            $table->unsignedBigInteger('net_cents')->default(0);
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('payslips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('employee_number');       // snapshot
            $table->char('currency', 3)->default('KES');
            $table->unsignedBigInteger('gross_earnings_cents')->default(0);
            $table->unsignedBigInteger('taxable_income_cents')->default(0);
            $table->unsignedBigInteger('statutory_deductions_cents')->default(0);
            $table->unsignedBigInteger('other_deductions_cents')->default(0);
            $table->unsignedBigInteger('total_deductions_cents')->default(0);
            $table->unsignedBigInteger('net_pay_cents')->default(0);
            $table->string('status')->default('draft');
            $table->json('snapshot')->nullable();     // bank/tax details at run time
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payslip_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('payslip_id')->constrained('payslips')->cascadeOnDelete();
            $table->string('type');           // earning | deduction
            $table->string('code');
            $table->string('name');
            $table->unsignedBigInteger('amount_cents');
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_statutory')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'payslip_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_periods');
    }
};
