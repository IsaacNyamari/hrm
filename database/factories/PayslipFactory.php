<?php

namespace Database\Factories;

use App\Enums\PayslipStatus;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payslip>
 */
class PayslipFactory extends Factory
{
    protected $model = Payslip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'payroll_run_id' => PayrollRun::factory(),
            'employee_id' => Employee::factory(),
            'employee_number' => 'EMP-'.fake()->unique()->numerify('######'),
            'currency' => 'KES',
            'gross_earnings_cents' => 10000000,
            'taxable_income_cents' => 10000000,
            'statutory_deductions_cents' => 1500000,
            'other_deductions_cents' => 0,
            'total_deductions_cents' => 1500000,
            'net_pay_cents' => 8500000,
            'status' => PayslipStatus::Draft,
        ];
    }
}
