<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code');
            $table->boolean('requires_expiry')->default(false);
            $table->json('notify_before_days')->nullable(); // e.g. [30, 14, 7]
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('employee_documents', function (Blueprint $table): void {
            $table->foreignId('document_type_id')->nullable()->after('category')->constrained('document_types')->nullOnDelete();
            $table->json('expiry_reminders_sent')->nullable()->after('expiry_date'); // day-marks already notified
        });
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('document_type_id');
            $table->dropColumn('expiry_reminders_sent');
        });

        Schema::dropIfExists('document_types');
    }
};
