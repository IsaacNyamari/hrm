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
            // The RFID/proximity card number printed on an employee's access
            // card, pushed to biometric terminals as an alternate verification
            // method alongside PIN+password and fingerprint.
            $table->string('device_card_number', 32)->nullable()->after('device_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('device_card_number');
        });
    }
};
