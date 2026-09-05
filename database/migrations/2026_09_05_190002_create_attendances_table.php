<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->date('work_date');
            $table->timestamp('clock_in_at')->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->timestamp('break_started_at')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('status')->default('present');
            $table->string('source')->default('web');

            $table->string('clock_in_ip', 45)->nullable();
            $table->string('clock_out_ip', 45)->nullable();
            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_lng', 10, 7)->nullable();
            $table->decimal('clock_out_lat', 10, 7)->nullable();
            $table->decimal('clock_out_lng', 10, 7)->nullable();
            $table->string('clock_in_location')->nullable();
            $table->string('clock_out_location')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'employee_id', 'work_date']);
            $table->index(['tenant_id', 'work_date']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('attendance_breaks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('minutes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'attendance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_breaks');
        Schema::dropIfExists('attendances');
    }
};
