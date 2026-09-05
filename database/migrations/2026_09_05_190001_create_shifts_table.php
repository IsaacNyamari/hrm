<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            // ISO weekday numbers 1 (Mon) .. 7 (Sun)
            $table->json('working_days');
            // Overtime counts only after this many worked minutes past shift end (null = disabled)
            $table->unsignedSmallInteger('overtime_after_minutes')->nullable();
            $table->boolean('is_night_shift')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('shift_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shifts');
    }
};
