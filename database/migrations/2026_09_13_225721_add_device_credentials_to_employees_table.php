<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // A numeric keypad password for biometric-terminal verification —
            // a fallback to fingerprint at the device, not the employee's app
            // login password. Encrypted at rest since it's still a credential.
            $table->text('device_password')->nullable()->after('employee_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('device_password');
        });
    }
};
