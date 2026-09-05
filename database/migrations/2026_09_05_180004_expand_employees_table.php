<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            // Personal
            $table->string('preferred_name')->nullable()->after('last_name');
            $table->string('personal_email')->nullable()->after('email');
            $table->string('alternate_phone')->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('alternate_phone');
            $table->string('gender')->nullable()->after('date_of_birth');
            $table->string('marital_status')->nullable()->after('gender');
            $table->string('nationality')->nullable()->after('marital_status');

            // Address
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->char('country', 2)->nullable();

            // Employment
            $table->foreignId('employment_type_id')->nullable()->after('status')->constrained('employment_types')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('employment_type_id')->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->after('department_id')->constrained('designations')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('designation_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->after('branch_id')->constrained('employees')->nullOnDelete();
            $table->date('probation_ends_at')->nullable();
            $table->date('confirmed_at')->nullable();
            $table->date('exit_date')->nullable();

            // Compensation summary (full payroll structures land in P8)
            $table->unsignedBigInteger('salary_amount_cents')->nullable();
            $table->char('salary_currency', 3)->nullable();
            $table->string('pay_frequency')->nullable(); // monthly | biweekly | weekly | daily | hourly

            // Misc
            $table->string('profile_photo_path')->nullable();
            $table->text('notes')->nullable();

            $table->index(['tenant_id', 'department_id']);
            $table->index(['tenant_id', 'branch_id']);
            $table->index(['tenant_id', 'manager_id']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'department_id']);
            $table->dropIndex(['tenant_id', 'branch_id']);
            $table->dropIndex(['tenant_id', 'manager_id']);

            $table->dropConstrainedForeignId('employment_type_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('designation_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('manager_id');

            $table->dropColumn([
                'preferred_name', 'personal_email', 'alternate_phone', 'date_of_birth',
                'gender', 'marital_status', 'nationality',
                'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country',
                'probation_ends_at', 'confirmed_at', 'exit_date',
                'salary_amount_cents', 'salary_currency', 'pay_frequency',
                'profile_photo_path', 'notes',
            ]);
        });
    }
};
