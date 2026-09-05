<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('provider');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('KES');
            $table->string('reference')->unique();          // our idempotency handle
            $table->string('provider_reference')->nullable()->index(); // provider txn id (e.g. CheckoutRequestID)
            $table->string('payer')->nullable();            // e.g. msisdn
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('provider');
            $table->string('type')->default('charge');      // charge | refund | webhook
            $table->string('status')->nullable();
            $table->string('external_id')->nullable();       // provider transaction id
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->char('currency', 3)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Idempotency: a given provider event is recorded at most once.
            $table->unique(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payments');
    }
};
