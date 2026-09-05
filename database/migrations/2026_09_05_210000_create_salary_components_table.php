<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code');
            $table->string('type');                 // earning | deduction
            $table->string('calculation_method');   // fixed | percentage | formula
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->decimal('rate', 8, 5)->nullable();
            $table->string('base_code')->nullable();
            $table->string('formula')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_pensionable')->default(true);
            $table->boolean('is_statutory')->default(false);
            $table->boolean('is_recurring')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Short explicit index name for MySQL compatibility
            $table->unique(
                ['tenant_id', 'code'],
                'salary_component_tenant_code_unique'
            );
        });

        Schema::create('salary_structures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('salary_structure_components', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('salary_structure_id')
                ->constrained('salary_structures')
                ->cascadeOnDelete();

            $table->foreignId('salary_component_id')
                ->constrained('salary_components')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->decimal('rate', 8, 5)->nullable();
            $table->timestamps();

            // Short explicit index name
            $table->unique(
                ['salary_structure_id', 'salary_component_id'],
                'salary_structure_component_unique'
            );
        });

        Schema::create('employee_salary_components', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->foreignId('salary_component_id')
                ->constrained('salary_components')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->decimal('rate', 8, 5)->nullable();
            $table->string('formula')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(
                ['tenant_id', 'employee_id'],
                'employee_salary_tenant_employee_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('salary_structure_components');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('salary_components');
    }
};
