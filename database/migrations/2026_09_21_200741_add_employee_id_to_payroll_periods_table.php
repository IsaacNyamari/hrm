<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A period with an employee_id is an off-cycle final settlement for that
     * (exited) employee; regular periods leave it null.
     */
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->foreignId('employee_id')->nullable()->after('tenant_id')->constrained('employees')->cascadeOnDelete();
            $table->unique(['tenant_id', 'start_date', 'end_date', 'employee_id'], 'payroll_periods_window_employee_unique');
        });

        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'start_date', 'end_date']);
        });

        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->dropUnique('payroll_periods_window_employee_unique');
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
