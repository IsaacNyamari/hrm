<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\Payslip;
use Illuminate\Contracts\View\Factory as ViewFactory;
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
     * A print-ready payslip document. The Blade uses PDF-safe markup so a PDF
     * renderer (e.g. barryvdh/laravel-dompdf) can be dropped in later by
     * swapping the final response for `Pdf::loadView(...)->download()`.
     */
    public function pdf(Payslip $payslip, ViewFactory $view): Response
    {
        $this->authorize('viewPayslip', $payslip);

        $payslip->load(['lines', 'employee', 'run.period']);

        $filename = "payslip-{$payslip->employee_number}-{$payslip->run->period->pay_date->format('Y-m')}.html";

        $html = $view->make('payroll.payslip', [
            'payslip' => $payslip,
            'tenant' => Tenancy::getOrFail(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
