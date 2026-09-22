<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\Payroll\RenderPayslipPdf;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\PayslipDetailResource;
use App\Http\Resources\Api\V1\Mobile\PayslipSummaryResource;
use App\Models\Employee;
use App\Models\Payslip;
use App\Services\SelfService\SelfServiceDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The token owner's released payslips (approved, paid or locked runs only —
 * drafts still being checked by payroll never show), with the same year-to-date
 * totals as "My HR", the full breakdown and the receipt PDF.
 */
class PayslipController extends Controller
{
    public function index(Request $request, SelfServiceDashboard $dashboard): AnonymousResourceCollection
    {
        $employee = $this->employee($request);
        $year = CarbonImmutable::now()->year;

        $payslips = Payslip::query()
            ->where('employee_id', $employee->getKey())
            ->released()
            ->with('run.period:id,name,pay_date')
            ->latest('id')
            ->paginate(min($request->integer('per_page', 24), 50));

        $salary = $dashboard->salary($employee, $year);

        return PayslipSummaryResource::collection($payslips)->additional(['meta' => [
            'year' => $year,
            'ytd_gross_cents' => $salary['ytd_gross_cents'],
            'ytd_deductions_cents' => $salary['ytd_deductions_cents'],
            'ytd_net_cents' => $salary['ytd_net_cents'],
        ]]);
    }

    public function show(Request $request, string $payslip): PayslipDetailResource
    {
        $record = $this->find($request, $payslip);

        return new PayslipDetailResource($record->load(['lines', 'employee:id,first_name,middle_name,last_name,job_title']));
    }

    public function pdf(Request $request, string $payslip, RenderPayslipPdf $renderer): Response
    {
        $record = $this->find($request, $payslip);

        return $renderer->download($record, Tenancy::getOrFail());
    }

    private function find(Request $request, string $uuid): Payslip
    {
        $employee = $this->employee($request);

        $record = Payslip::query()
            ->where('uuid', $uuid)
            ->where('employee_id', $employee->getKey())
            ->released()
            ->with('run.period')
            ->firstOrFail();

        $this->authorize('viewPayslip', $record);

        return $record;
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user()?->employee;
        abort_if($employee === null, 422, 'Your account is not linked to an employee record.');

        return $employee;
    }
}
