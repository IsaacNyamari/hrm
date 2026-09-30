<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->string('payment_reference')->nullable()->after('paid_at');
            $table->foreignId('paid_by')->nullable()->after('payment_reference')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('paid_by');
            $table->dropColumn('payment_reference');
        });
    }
};
