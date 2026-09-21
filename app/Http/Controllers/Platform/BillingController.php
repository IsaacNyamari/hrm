<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Actions\Billing\RenderInvoicePdf;
use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    /**
     * Single payment detail. The list/browse screens are the
     * App\Livewire\Platform\Billing component.
     */
    public function showPayment(Request $request, Payment $payment): View
    {
        $user = $request->user();
        abort_unless($user !== null && $user->canOnPlatform(PlatformPermission::PaymentsView), 403);

        $payment->load(['tenant:id,name,slug', 'invoice:id,uuid,number,total_cents,currency', 'transactions']);

        return view('platform.payment', ['payment' => $payment]);
    }

    /**
     * Any operator who can see invoices (billing or payments) can download them.
     */
    public function downloadInvoice(Request $request, Invoice $invoice, RenderInvoicePdf $renderer): Response
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ($user->canOnPlatform(PlatformPermission::InvoicesManage) || $user->canOnPlatform(PlatformPermission::PaymentsView)),
            403,
        );

        return $renderer->download($invoice);
    }
}
