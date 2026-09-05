<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('reference');           // e.g. HR-000123, unique per tenant
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type')->default('other');
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('priority')->default('normal'); // low | normal | high
            $table->string('status')->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('hr_request_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('hr_request_id')->constrained('hr_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false); // HR note, not shown to the employee
            $table->timestamps();

            $table->index(['tenant_id', 'hr_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_request_comments');
        Schema::dropIfExists('hr_requests');
    }
};
