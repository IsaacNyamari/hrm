<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('type')->default('annual');
            $table->string('status')->default('draft');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('self_review_enabled')->default(true);
            $table->boolean('peer_review_enabled')->default(false);
            $table->unsignedTinyInteger('goal_weight')->default(60);
            $table->unsignedTinyInteger('competency_weight')->default(40);
            $table->json('rating_scale')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('performance_cycle_id')->nullable()->constrained('performance_cycles')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('business');
            $table->unsignedTinyInteger('weight')->default(0); // relative %, normalised at scoring time
            $table->string('metric')->nullable();
            $table->string('target')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('progress')->default(0); // 0-100
            $table->decimal('self_rating', 4, 2)->nullable();
            $table->decimal('manager_rating', 4, 2)->nullable();
            $table->text('manager_comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'status']);
            $table->index(['tenant_id', 'performance_cycle_id']);
        });

        Schema::create('goal_check_ins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('goal_id')->constrained('goals')->cascadeOnDelete();
            $table->unsignedTinyInteger('progress'); // 0-100 at the time of the check-in
            $table->text('note')->nullable();
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'goal_id']);
        });

        Schema::create('kpis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('performance_cycle_id')->nullable()->constrained('performance_cycles')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('goal_id')->nullable()->constrained('goals')->nullOnDelete();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->string('direction')->default('higher_is_better');
            $table->decimal('baseline_value', 16, 4)->nullable();
            $table->decimal('target_value', 16, 4);
            $table->decimal('current_value', 16, 4)->default(0);
            $table->unsignedTinyInteger('weight')->default(0);
            $table->timestamp('last_recorded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
        });

        Schema::create('performance_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('performance_cycle_id')->constrained('performance_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_type'); // self | manager | peer | upward
            $table->string('status')->default('pending');
            $table->decimal('overall_rating', 4, 2)->nullable();
            $table->decimal('final_score', 4, 2)->nullable(); // goal + competency weighting, set on close
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledgement_note')->nullable();
            $table->timestamps();

            $table->unique(['performance_cycle_id', 'employee_id', 'reviewer_id', 'reviewer_type'], 'perf_review_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'reviewer_id', 'status']);
        });

        Schema::create('review_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('performance_review_id')->constrained('performance_reviews')->cascadeOnDelete();
            $table->string('competency');
            $table->unsignedTinyInteger('weight')->default(0);
            $table->decimal('rating', 4, 2);
            $table->string('comment')->nullable();
            $table->timestamps();

            $table->unique(['performance_review_id', 'competency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_ratings');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('kpis');
        Schema::dropIfExists('goal_check_ins');
        Schema::dropIfExists('goals');
        Schema::dropIfExists('performance_cycles');
    }
};
