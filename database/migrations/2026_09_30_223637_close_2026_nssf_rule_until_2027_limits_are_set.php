<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The KES 108,000 NSSF upper earnings limit applies to Feb 2026 – Jan 2027. The
 * Feb 2027 limit is not yet gazetted, so end the rule instead of letting it
 * silently keep capping contributions. Customised rules are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('tax_rules')->where('type', 'nssf')->where('effective_from', '2026-02-01')->whereNull('effective_to')->get() as $rule) {
            $config = json_decode((string) $rule->config, true) ?: [];

            if ((int) ($config['ceiling_cents'] ?? 0) === 10_800_000) {
                DB::table('tax_rules')->where('id', $rule->id)->update(['effective_to' => '2027-01-31']);
            }
        }
    }

    public function down(): void
    {
        DB::table('tax_rules')->where('type', 'nssf')->where('effective_from', '2026-02-01')->where('effective_to', '2027-01-31')->update(['effective_to' => null]);
    }
};
