<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_branding', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('brand_name')->nullable();
            $table->string('logo_disk')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 9)->default('#4f46e5');
            $table->string('accent_color', 9)->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('reply_to_email')->nullable();
            $table->string('support_email')->nullable();
            $table->text('email_footer')->nullable();
            $table->text('email_signature')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('category');
            $table->json('channels'); // {"mail": bool}
            $table->timestamps();

            $table->unique(['user_id', 'category']);
        });

        Schema::table('announcements', function (Blueprint $table): void {
            $table->boolean('is_pinned')->default(false)->after('audience');
            $table->boolean('acknowledgement_required')->default(false)->after('is_pinned');
        });

        Schema::table('announcement_reads', function (Blueprint $table): void {
            $table->timestamp('acknowledged_at')->nullable()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('announcement_reads', fn (Blueprint $table) => $table->dropColumn('acknowledged_at'));
        Schema::table('announcements', fn (Blueprint $table) => $table->dropColumn(['is_pinned', 'acknowledgement_required']));
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('tenant_branding');
    }
};
