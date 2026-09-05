<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('event');
            $table->nullableMorphs('actor');       // actor_type / actor_id
            $table->string('actor_label')->nullable();
            $table->nullableMorphs('auditable');    // auditable_type / auditable_id
            $table->string('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('via_impersonation')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'event']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
