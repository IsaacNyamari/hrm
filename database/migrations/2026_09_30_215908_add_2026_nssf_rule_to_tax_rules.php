<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * NSSF upper earnings limit moved to KES 108,000 from Feb 2026. Tenants
 * provisioned earlier only have the KES 72,000 rule open-ended, so close it
 * and add the new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stale = DB::table('tax_rules')
            ->where('type', 'nssf')
            ->where('country', 'KE')
            ->whereNull('effective_to')
            ->where('effective_from', '<', '2026-02-01')
            ->get();

        foreach ($stale as $rule) {
            $config = json_decode((string) $rule->config, true) ?: [];

            if ((int) ($config['ceiling_cents'] ?? 0) !== 7_200_000) {
                continue; // customised by the tenant, leave it alone
            }

            DB::table('tax_rules')->where('id', $rule->id)->update(['effective_to' => '2026-01-31']);

            $exists = DB::table('tax_rules')
                ->where('tenant_id', $rule->tenant_id)
                ->where('type', 'nssf')
                ->where('effective_from', '2026-02-01')
                ->exists();

            if (! $exists) {
                DB::table('tax_rules')->insert([
                    'tenant_id' => $rule->tenant_id,
                    'uuid' => (string) Str::uuid(),
                    'type' => 'nssf',
                    'name' => $rule->name,
                    'country' => 'KE',
                    'effective_from' => '2026-02-01',
                    'effective_to' => null,
                    'config' => json_encode(['rate' => 0.06, 'ceiling_cents' => 10_800_000]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('tax_rules')->where('type', 'nssf')->where('effective_from', '2026-02-01')->delete();
        DB::table('tax_rules')->where('type', 'nssf')->where('effective_to', '2026-01-31')->update(['effective_to' => null]);
    }
};
