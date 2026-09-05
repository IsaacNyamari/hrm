<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('code');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained('employment_types')->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('vacancies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('job_position_id')->nullable()->constrained('job_positions')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained('employment_types')->nullOnDelete();
            $table->foreignId('hiring_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->unsignedSmallInteger('positions_filled')->default(0);
            $table->string('status')->default('draft');
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_remote')->default(false);
            $table->unsignedBigInteger('min_salary_cents')->nullable();
            $table->unsignedBigInteger('max_salary_cents')->nullable();
            $table->string('salary_currency', 3)->nullable();
            $table->json('pipeline_stages');
            $table->timestamp('published_at')->nullable();
            $table->date('closes_on')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('applicants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('location')->nullable();
            $table->string('resume_disk')->nullable();
            $table->string('resume_path')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('source')->default('careers_site');
            $table->foreignId('referred_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'email']);
        });

        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('stage');
            $table->string('status')->default('active');
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5 recruiter score
            $table->text('cover_letter')->nullable();
            $table->unsignedBigInteger('expected_salary_cents')->nullable();
            $table->string('source')->default('careers_site');
            $table->string('rejected_reason')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at');
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['vacancy_id', 'applicant_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'vacancy_id', 'stage']);
        });

        Schema::create('application_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('type'); // stage_change | note | rating | rejected | interview | offer
            $table->string('from_stage')->nullable();
            $table->string('to_stage')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'application_id']);
        });

        Schema::create('interviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->string('mode')->default('video');
            $table->foreignId('interviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(45);
            $table->string('location')->nullable();
            $table->string('meeting_url')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('recommendation')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('feedback_submitted_at')->nullable();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'application_id']);
            $table->index(['tenant_id', 'interviewer_id', 'status']);
        });

        Schema::create('offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('job_title');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained('employment_types')->nullOnDelete();
            $table->unsignedBigInteger('salary_cents');
            $table->string('salary_currency', 3)->default('KES');
            $table->date('start_date')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('converted_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('application_activities');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('applicants');
        Schema::dropIfExists('vacancies');
        Schema::dropIfExists('job_positions');
    }
};
