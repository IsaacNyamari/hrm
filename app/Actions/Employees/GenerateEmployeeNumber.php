<?php

declare(strict_types=1);

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/**
 * Builds the next employee number for a tenant from its configured format.
 *
 * Format placeholders (tenant setting `employee_number.format`, default
 * "EMP-{SEQ}"):
 *   {SEQ}    zero-padded running number (width = `employee_number.padding`, def 6)
 *   {PREFIX} upper-cased tenant slug
 *   {YEAR}   current year
 */
class GenerateEmployeeNumber
{
    public function __construct(private readonly TenantContext $context) {}

    public function forTenant(Tenant $tenant): string
    {
        return $this->context->run($tenant, function () use ($tenant): string {
            $settings = $tenant->settings['employee_number'] ?? [];
            $format = (string) ($settings['format'] ?? 'EMP-{SEQ}');
            $padding = (int) ($settings['padding'] ?? 6);
            $prefix = Str::of($tenant->slug)->replace('-', '')->limit(6, '')->upper()->value();

            $sequence = Employee::withTrashed()->count() + 1;

            do {
                $number = strtr($format, [
                    '{SEQ}' => str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
                    '{PREFIX}' => $prefix,
                    '{YEAR}' => date('Y'),
                ]);
                $sequence++;
            } while (Employee::withTrashed()->where('employee_number', $number)->exists());

            return $number;
        });
    }
}
