<?php

declare(strict_types=1);

use App\Enums\PaymentProvider;
use App\Services\Payment\PaymentManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->boolean('is_live')->default(true)->after('status');
        });

        // Payments taken while a provider was still on test keys or the sandbox
        // moved no money: if the provider is not live now, nothing it took so
        // far can have been either, so keep it out of collected revenue.
        $gateways = app(PaymentManager::class);

        foreach ([PaymentProvider::Paystack, PaymentProvider::Mpesa] as $provider) {
            if (! $gateways->isLive($provider)) {
                DB::table('payments')->where('provider', $provider->value)->update(['is_live' => false]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('is_live');
        });
    }
};
