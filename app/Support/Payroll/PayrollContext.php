<?php

declare(strict_types=1);

namespace App\Support\Payroll;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use Carbon\CarbonImmutable;

/**
 * Per-employee, per-run computation state. Earnings are added as they are
 * resolved; {@see self::variables()} exposes everything computed so far to the
 * formula evaluator.
 */
class PayrollContext
{
    /** @var array<string, int> code => cents */
    public array $earnings = [];

    public function __construct(
        public readonly Employee $employee,
        public readonly PayrollPeriod $period,
        public readonly CarbonImmutable $payDate,
        public readonly int $basicSalaryCents,
        public readonly int $workingDays,
        public readonly int $daysWorked,
        public readonly float $overtimeHours,
        public readonly float $hourlyRate,
    ) {}

    public function addEarning(string $code, int $cents): void
    {
        $this->earnings[strtoupper($code)] = $cents;
    }

    public function grossCents(): int
    {
        return array_sum($this->earnings);
    }

    /**
     * @return array<string, float>
     */
    public function variables(): array
    {
        $vars = [
            'basic_salary' => (float) $this->basicSalaryCents,
            'gross_earnings' => (float) $this->grossCents(),
            'working_days' => (float) $this->workingDays,
            'days_worked' => (float) $this->daysWorked,
            'overtime_hours' => $this->overtimeHours,
            'overtime_rate' => $this->hourlyRate * 1.5,
            'hourly_rate' => $this->hourlyRate,
        ];

        foreach ($this->earnings as $code => $cents) {
            $vars[strtolower($code)] = (float) $cents;
        }

        return $vars;
    }
}
