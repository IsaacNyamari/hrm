<?php

declare(strict_types=1);

namespace App\Support\Payroll;

use App\Models\Tenant;

/**
 * An organization's pay rules, stored in tenants.settings['payroll'].
 *
 * - salary_basis: 'attendance' prorates basic salary by payable days (days
 *   present + approved paid leave) over working days; 'fixed' pays the full
 *   contractual basic regardless of attendance.
 * - overtime: paid automatically from attendance at overtime_multiplier ×
 *   hourly rate; hours worked on a rest day / public holiday use
 *   rest_day_multiplier.
 */
final readonly class PayRules
{
    public const BASIS_ATTENDANCE = 'attendance';

    public const BASIS_FIXED = 'fixed';

    public function __construct(
        public string $salaryBasis = self::BASIS_ATTENDANCE,
        public bool $overtimeEnabled = true,
        public float $overtimeMultiplier = 1.5,
        public float $restDayMultiplier = 2.0,
        public float $standardDailyHours = 8.0,
        public bool $requireSeparateApprover = true,
    ) {}

    public static function for(Tenant $tenant): self
    {
        $settings = (array) ($tenant->settings['payroll'] ?? []);
        $defaults = new self;

        return new self(
            salaryBasis: in_array($settings['salary_basis'] ?? null, [self::BASIS_ATTENDANCE, self::BASIS_FIXED], true)
                ? $settings['salary_basis']
                : $defaults->salaryBasis,
            overtimeEnabled: (bool) ($settings['overtime_enabled'] ?? $defaults->overtimeEnabled),
            overtimeMultiplier: self::positive($settings['overtime_multiplier'] ?? null, $defaults->overtimeMultiplier),
            restDayMultiplier: self::positive($settings['rest_day_multiplier'] ?? null, $defaults->restDayMultiplier),
            standardDailyHours: self::positive($settings['standard_daily_hours'] ?? null, $defaults->standardDailyHours),
            requireSeparateApprover: (bool) ($settings['require_separate_approver'] ?? $defaults->requireSeparateApprover),
        );
    }

    public function isAttendanceBased(): bool
    {
        return $this->salaryBasis === self::BASIS_ATTENDANCE;
    }

    /**
     * @return array{salary_basis: string, overtime_enabled: bool, overtime_multiplier: float, rest_day_multiplier: float, standard_daily_hours: float, require_separate_approver: bool}
     */
    public function toArray(): array
    {
        return [
            'salary_basis' => $this->salaryBasis,
            'overtime_enabled' => $this->overtimeEnabled,
            'overtime_multiplier' => $this->overtimeMultiplier,
            'rest_day_multiplier' => $this->restDayMultiplier,
            'standard_daily_hours' => $this->standardDailyHours,
            'require_separate_approver' => $this->requireSeparateApprover,
        ];
    }

    private static function positive(mixed $value, float $default): float
    {
        return is_numeric($value) && (float) $value > 0 ? (float) $value : $default;
    }
}
