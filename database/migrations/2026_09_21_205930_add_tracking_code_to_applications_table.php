<?php

use App\Models\Application;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A short, human-friendly code candidates use to track an application
     * without an account (e.g. APP-7K3Q9XPA).
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('tracking_code', 16)->nullable()->unique()->after('uuid');
        });

        DB::table('applications')->whereNull('tracking_code')->orderBy('id')->each(function (object $row): void {
            DB::table('applications')->where('id', $row->id)->update(['tracking_code' => Application::newTrackingCode()]);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropUnique(['tracking_code']);
            $table->dropColumn('tracking_code');
        });
    }
};
