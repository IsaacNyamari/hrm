<?php

declare(strict_types=1);

namespace App\Support\Payroll;

use App\Enums\PayrollFrequency;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * The pay cycle a date falls in: the regular payroll period covering it, or
 * the calendar month when periods have not been set up that far ahead.
 */
final class CurrentPayCycle
{
    public static function for(Tenant $tenant, CarbonImmutable $date): PayrollPeriod
    {
        $period = PayrollPeriod::query()->regular()
            ->where('tenant_id', $tenant->getKey())
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();

        if ($period !== null) {
            return $period->setRelation('tenant', $tenant);
        }

        $period = new PayrollPeriod([
            'name' => $date->format('F Y'),
            'frequency' => PayrollFrequency::Monthly,
            'start_date' => $date->startOfMonth(),
            'end_date' => $date->endOfMonth()->startOfDay(),
            'pay_date' => $date->endOfMonth()->startOfDay(),
        ]);
        $period->tenant_id = $tenant->getKey();

        return $period->setRelation('tenant', $tenant);
    }
}
