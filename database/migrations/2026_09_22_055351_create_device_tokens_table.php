<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expo push tokens registered by the mobile app. A token belongs to one
     * device, so re-registering it from another account moves it. Tokens are
     * tied to the Sanctum token that registered them so signing a device out
     * (or revoking its session) also stops its push notifications.
     */
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')->nullable()->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->string('expo_push_token')->unique();
            $table->string('platform', 16);                        // ios | android
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
