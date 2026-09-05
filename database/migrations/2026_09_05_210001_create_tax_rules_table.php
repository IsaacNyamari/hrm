<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('type');            // paye | nssf | shif | housing_levy | pension
            $table->char('country', 2)->default('KE');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->json('config');            // bands / rates / caps — the whole scheme
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
