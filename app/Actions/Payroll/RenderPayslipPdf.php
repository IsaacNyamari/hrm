<?php

declare(strict_types=1);

namespace App\Actions\Payroll;

use App\Models\Payslip;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a payslip as the A5 receipt-style PDF. Shared by the web payslip
 * download and the mobile API so both produce the identical document; callers
 * authorise first.
 */
class RenderPayslipPdf
{
    public function download(Payslip $payslip, Tenant $tenant): Response
    {
        $payslip->loadMissing(['lines', 'employee', 'run.period']);
        $tenant->loadMissing('branding');

        return Pdf::loadView('payroll.payslip', ['payslip' => $payslip, 'tenant' => $tenant])
            ->setPaper('a5')
            ->setOption('isFontSubsettingEnabled', true)
            ->download($this->filename($payslip));
    }

    public function filename(Payslip $payslip): string
    {
        return 'payslip-'.Str::slug($payslip->employee_number.'-'.$payslip->run->period->name).'.pdf';
    }
}
