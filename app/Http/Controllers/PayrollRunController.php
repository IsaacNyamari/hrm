<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollRunController extends Controller
{
    public function __construct(private readonly PayrollService $service) {}

    public function store(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $this->authorize('process', $payrollPeriod);

        $data = $request->validate([
            'employee_uuids' => ['sometimes', 'array'],
            'employee_uuids.*' => ['string'],
        ]);

        $run = $this->service->run($payrollPeriod, $data['employee_uuids'] ?? [], $request->user());

        return response()->json(['data' => $this->transform($run->fresh())], 201);
    }

    public function show(PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('view', $payrollRun);

        return response()->json(['data' => $this->transform($payrollRun->load('payslips.employee:id,first_name,last_name'))]);
    }

    public function approve(PayrollRun $payrollRun, Request $request): JsonResponse
    {
        $this->authorize('approve', $payrollRun);

        return response()->json(['data' => $this->transform($this->service->approve($payrollRun, $request->user()))]);
    }

    public function pay(PayrollRun $payrollRun, Request $request): JsonResponse
    {
        $this->authorize('approve', $payrollRun);

        $validated = $request->validate(['payment_reference' => ['required', 'string', 'max:120']]);

        return response()->json(['data' => $this->transform($this->service->markPaid($payrollRun, $validated['payment_reference'], $request->user()))]);
    }

    public function lock(PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('approve', $payrollRun);

        return response()->json(['data' => $this->transform($this->service->lock($payrollRun))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(PayrollRun $run): array
    {
        return [
            'uuid' => $run->uuid,
            'status' => $run->status->value,
            'employee_count' => $run->employee_count,
            'gross_cents' => $run->gross_cents,
            'taxable_cents' => $run->taxable_cents,
            'statutory_cents' => $run->statutory_cents,
            'deductions_cents' => $run->deductions_cents,
            'net_cents' => $run->net_cents,
            'processed_at' => $run->processed_at?->toIso8601String(),
            'approved_at' => $run->approved_at?->toIso8601String(),
            'paid_at' => $run->paid_at?->toIso8601String(),
            'locked_at' => $run->locked_at?->toIso8601String(),
            'payslips' => $run->relationLoaded('payslips') ? $run->payslips->map(fn ($p): array => [
                'uuid' => $p->uuid,
                'employee' => $p->employee?->fullName(),
                'gross_earnings_cents' => $p->gross_earnings_cents,
                'total_deductions_cents' => $p->total_deductions_cents,
                'net_pay_cents' => $p->net_pay_cents,
            ])->all() : null,
        ];
    }
}
