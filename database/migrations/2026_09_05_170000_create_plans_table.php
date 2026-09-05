<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->char('currency', 3)->default('KES');
            $table->string('billing_interval')->default('monthly');
            $table->unsignedInteger('employee_limit')->nullable();   // null = unlimited
            $table->unsignedBigInteger('storage_limit_mb')->nullable();
            $table->json('features')->nullable();
            $table->unsignedInteger('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
