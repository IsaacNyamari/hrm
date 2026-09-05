<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type')->default('loan'); // loan | salary_advance
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('principal_cents');
            $table->decimal('interest_rate', 6, 4)->default(0); // annual, simple
            $table->unsignedSmallInteger('installments');
            $table->unsignedBigInteger('installment_cents');
            $table->unsignedBigInteger('outstanding_cents');
            $table->date('start_date');
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'status']);
        });

        Schema::create('loan_repayments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('payslip_id')->nullable()->constrained('payslips')->nullOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->date('paid_on');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'loan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('loans');
    }
};
