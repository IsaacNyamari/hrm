<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Employer statutory costs: give existing tenants the NITA levy rule (KES 50 per
 * employee per month) and an explicit employer rate on the NSSF and Housing Levy
 * rules (both match the employee's 6% / 1.5%).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['nssf' => 0.06, 'housing_levy' => 0.015] as $type => $rate) {
            foreach (DB::table('tax_rules')->where('type', $type)->get() as $rule) {
                $config = json_decode((string) $rule->config, true) ?: [];

                if (! array_key_exists('employer_rate', $config)) {
                    $config['employer_rate'] = $config['rate'] ?? $rate;
                    DB::table('tax_rules')->where('id', $rule->id)->update(['config' => json_encode($config)]);
                }
            }
        }

        $tenantIds = DB::table('tax_rules')->where('country', 'KE')->distinct()->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            if (DB::table('tax_rules')->where('tenant_id', $tenantId)->where('type', 'nita')->exists()) {
                continue;
            }

            DB::table('tax_rules')->insert([
                'tenant_id' => $tenantId,
                'uuid' => (string) Str::uuid(),
                'type' => 'nita',
                'name' => 'NITA Levy (Kenya)',
                'country' => 'KE',
                'effective_from' => '2024-07-01',
                'effective_to' => null,
                'config' => json_encode(['amount_cents' => 5_000]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tax_rules')->where('type', 'nita')->delete();
    }
};
