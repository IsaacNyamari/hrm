<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payroll\RenderPayslipPdf;
use App\Facades\Tenancy;
use App\Models\Payslip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PayslipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $canViewAll = $user?->can('payroll.view') || $user?->can('payroll.manage');
        $ownEmployeeId = $user?->employee?->getKey();

        // Own payslips need no permission; seeing everyone's needs payroll.view.
        abort_if(! $canViewAll && $ownEmployeeId === null, 403);

        $payslips = Payslip::query()
            ->with('run.period:id,name,pay_date')
            ->when(! $canViewAll, fn ($q) => $q->where('employee_id', $ownEmployeeId))
            ->when($request->filled('run'), fn ($q) => $q->whereRelation('run', 'uuid', $request->string('run')->toString()))
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json($payslips->through(fn (Payslip $p): array => [
            'uuid' => $p->uuid,
            'period' => $p->run->period->name,
            'pay_date' => $p->run->period->pay_date->toDateString(),
            'gross_earnings_cents' => $p->gross_earnings_cents,
            'net_pay_cents' => $p->net_pay_cents,
            'status' => $p->status->value,
        ])->toArray());
    }

    public function show(Payslip $payslip): JsonResponse
    {
        $this->authorize('viewPayslip', $payslip);

        $payslip->load(['lines', 'employee:id,first_name,middle_name,last_name', 'run.period']);

        return response()->json(['data' => [
            'uuid' => $payslip->uuid,
            'employee' => $payslip->employee->fullName(),
            'employee_number' => $payslip->employee_number,
            'period' => $payslip->run->period->name,
            'pay_date' => $payslip->run->period->pay_date->toDateString(),
            'currency' => $payslip->currency,
            'gross_earnings_cents' => $payslip->gross_earnings_cents,
            'taxable_income_cents' => $payslip->taxable_income_cents,
            'statutory_deductions_cents' => $payslip->statutory_deductions_cents,
            'total_deductions_cents' => $payslip->total_deductions_cents,
            'net_pay_cents' => $payslip->net_pay_cents,
            'status' => $payslip->status->value,
            'earnings' => $payslip->lines->where('type.value', 'earning')->map(fn ($l): array => ['name' => $l->name, 'amount_cents' => $l->amount_cents])->values(),
            'deductions' => $payslip->lines->where('type.value', 'deduction')->map(fn ($l): array => ['name' => $l->name, 'amount_cents' => $l->amount_cents, 'statutory' => $l->is_statutory])->values(),
        ]]);
    }

    /**
     * The payslip as a downloadable, receipt-style PDF (A5).
     */
    public function pdf(Payslip $payslip, RenderPayslipPdf $renderer): Response
    {
        $this->authorize('viewPayslip', $payslip);

        return $renderer->download($payslip, Tenancy::getOrFail());
    }
}
