<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Enums\TaxRuleType;
use App\Models\TaxRule;
use Carbon\CarbonImmutable;

/**
 * Finds statutory rules that have lapsed or are about to, so a legislative
 * change is handled before payroll is run rather than after a wrong deduction.
 * Payroll itself refuses to run without a rule in force for the pay date.
 */
class StatutoryRuleWatch
{
    public const LEAD_DAYS = 90;

    /**
     * @return list<array{type: string, label: string, level: 'danger'|'warning', message: string}>
     */
    public function alerts(?CarbonImmutable $today = null, int $leadDays = self::LEAD_DAYS): array
    {
        $today ??= CarbonImmutable::today();
        $alerts = [];

        foreach (TaxRuleType::required() as $type) {
            $current = TaxRule::inForce($type, $today);

            if ($current === null) {
                $alerts[] = [
                    'type' => $type->value,
                    'label' => $type->label(),
                    'level' => 'danger',
                    'message' => "No {$type->label()} rule is in force today. Payroll cannot run until one is added.",
                ];

                continue;
            }

            $ends = $current->effective_to;

            if ($ends === null || $ends->greaterThan($today->addDays($leadDays))) {
                continue;
            }

            if (TaxRule::inForce($type, $ends->addDay()) !== null) {
                continue;
            }

            $alerts[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'level' => $ends->lessThanOrEqualTo($today->addDays(14)) ? 'danger' : 'warning',
                'message' => "The {$type->label()} rule ends on {$ends->format('d M Y')} and no rule follows it. Payroll dated after that day will be blocked until you add the new rates.",
            ];
        }

        return $alerts;
    }
}
