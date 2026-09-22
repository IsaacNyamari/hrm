<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Mobile;

use App\Enums\SalaryComponentType;
use App\Models\Payslip;
use App\Models\PayslipLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payslip's full breakdown, as on the receipt. Zero-value lines are left out
 * (the receipt hides them too). Expects `lines`, `employee` and `run.period`.
 *
 * @mixin Payslip
 */
class PayslipDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lines = $this->lines->filter(fn (PayslipLine $line): bool => $line->amount_cents !== 0);

        $map = fn (PayslipLine $line): array => [
            'code' => $line->code,
            'name' => $line->name,
            'amount_cents' => $line->amount_cents,
            'statutory' => $line->is_statutory,
        ];

        return [
            'uuid' => $this->uuid,
            'employee' => $this->employee->fullName(),
            'employee_number' => $this->employee_number,
            'job_title' => $this->employee->job_title,
            'period' => $this->run->period->name,
            'pay_date' => $this->run->period->pay_date->toDateString(),
            'currency' => $this->currency,
            'gross_earnings_cents' => $this->gross_earnings_cents,
            'taxable_income_cents' => $this->taxable_income_cents,
            'statutory_deductions_cents' => $this->statutory_deductions_cents,
            'other_deductions_cents' => $this->other_deductions_cents,
            'total_deductions_cents' => $this->total_deductions_cents,
            'net_pay_cents' => $this->net_pay_cents,
            'status' => $this->status->value,
            'earnings' => $lines->where('type', SalaryComponentType::Earning)->map($map)->values()->all(),
            'deductions' => $lines->where('type', SalaryComponentType::Deduction)->map($map)->values()->all(),
        ];
    }
}
