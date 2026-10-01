<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a subscription invoice as a downloadable PDF. Shared by the tenant
 * billing page and the platform billing screens; callers authorise first.
 */
class RenderInvoicePdf
{
    public function download(Invoice $invoice): Response
    {
        $invoice->loadMissing(['tenant', 'items', 'subscription.plan', 'payments']);

        return Pdf::loadView('billing.invoice-pdf', ['invoice' => $invoice])
            ->setPaper('a4')
            ->download($this->filename($invoice));
    }

    public function output(Invoice $invoice): string
    {
        $invoice->loadMissing(['tenant', 'items', 'subscription.plan', 'payments']);

        return Pdf::loadView('billing.invoice-pdf', ['invoice' => $invoice])->setPaper('a4')->output();
    }

    public function filename(Invoice $invoice): string
    {
        return 'invoice-'.Str::slug($invoice->number).'.pdf';
    }
}
