<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\RenderInvoicePdf;
use App\Enums\Permission;
use App\Facades\Tenancy;
use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    /**
     * Browsers get the billing page — the `subscription` middleware sends every
     * user of a lapsed tenant here, so it renders for all of them. JSON clients
     * get the billing summary, which requires `billing.view`.
     */
    public function index(Request $request): JsonResponse|View
    {
        if (! $request->expectsJson()) {
            return view('billing.index');
        }

        abort_unless($request->user()?->can(Permission::BillingView->value), 403);

        $tenant = Tenancy::getOrFail();
        $subscription = $tenant->subscription;

        return response()->json([
            'data' => [
                'status' => $subscription?->status->value,
                'plan' => $subscription?->plan->only(['name', 'slug', 'price_cents', 'currency']),
                'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
                'current_period_end' => $subscription?->current_period_end?->toIso8601String(),
                'has_app_access' => $tenant->hasAppAccess(),
                'invoices' => Invoice::query()
                    ->latest()
                    ->limit(20)
                    ->get(['uuid', 'number', 'status', 'total_cents', 'amount_paid_cents', 'currency', 'period_start', 'period_end', 'paid_at']),
            ],
        ]);
    }

    /**
     * The invoice is bound through TenantScope, so another tenant's invoice 404s.
     */
    public function invoicePdf(Request $request, Invoice $invoice, RenderInvoicePdf $renderer): Response
    {
        abort_unless($request->user()?->can(Permission::BillingView->value), 403);

        return $renderer->download($invoice);
    }
}
