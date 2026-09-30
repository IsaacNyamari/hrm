<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_statutory_details', function (Blueprint $table): void {
            $table->text('national_id')->nullable()->after('employee_id'); // encrypted
        });
    }

    public function down(): void
    {
        Schema::table('employee_statutory_details', function (Blueprint $table): void {
            $table->dropColumn('national_id');
        });
    }
};
