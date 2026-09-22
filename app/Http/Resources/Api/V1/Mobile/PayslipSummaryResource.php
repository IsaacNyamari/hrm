<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Mobile;

use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payslip in the employee's list. Expects `run.period` loaded.
 *
 * @mixin Payslip
 */
class PayslipSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'period' => $this->run->period->name,
            'pay_date' => $this->run->period->pay_date->toDateString(),
            'currency' => $this->currency,
            'gross_earnings_cents' => $this->gross_earnings_cents,
            'total_deductions_cents' => $this->total_deductions_cents,
            'net_pay_cents' => $this->net_pay_cents,
            'status' => $this->status->value,
        ];
    }
}
