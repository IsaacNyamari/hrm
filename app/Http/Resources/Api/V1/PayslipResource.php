<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payslip
 */
class PayslipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'employee_number' => $this->employee_number,
            'currency' => $this->currency,
            'gross_earnings_cents' => $this->gross_earnings_cents,
            'statutory_deductions_cents' => $this->statutory_deductions_cents,
            'other_deductions_cents' => $this->other_deductions_cents,
            'total_deductions_cents' => $this->total_deductions_cents,
            'net_pay_cents' => $this->net_pay_cents,
            'status' => $this->status->value,
            'period' => $this->whenLoaded('run', fn () => $this->run->relationLoaded('period') ? $this->run->period->name : null),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line): array => [
                'code' => $line->code,
                'name' => $line->name,
                'type' => $line->type->value,
                'amount_cents' => $line->amount_cents,
            ])),
        ];
    }
}
