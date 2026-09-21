<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\PaymentReconciler;
use App\Support\Payment\WebhookEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Where Paystack sends the payer back after hosted checkout. The query string
 * is never trusted: the charge is re-verified with Paystack server-side and
 * applied through the same idempotent reconciler as the webhook.
 */
class PaystackCallbackController extends Controller
{
    public function __invoke(Request $request, PaymentManager $payments, PaymentReconciler $reconciler): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');

        // TenantScope limits this to the current tenant's own payments.
        $payment = Payment::query()
            ->where('provider', PaymentProvider::Paystack)
            ->where('reference', $reference)
            ->first();

        if ($payment === null) {
            return to_route('billing.index')->with('billing.status', ['danger', __('We could not find that payment.')]);
        }

        try {
            $result = $payments->gateway(PaymentProvider::Paystack)->verifyPayment($payment->reference);
        } catch (Throwable $exception) {
            report($exception);

            return to_route('billing.index')->with('billing.status', ['warning', __('We could not confirm the payment yet. It will update automatically once Paystack confirms it.')]);
        }

        if ($result->status !== PaymentStatus::Pending && $result->externalId !== null) {
            $reconciler->reconcile(new WebhookEvent(
                provider: PaymentProvider::Paystack->value,
                status: $result->status,
                verified: true,
                reference: $payment->reference,
                externalId: $result->externalId,
                amountCents: $result->amountCents,
                currency: $payment->currency,
                failureReason: $result->failureReason,
                payload: $result->raw,
            ));
        }

        return to_route('billing.index')->with('billing.status', match ($result->status) {
            PaymentStatus::Succeeded => ['success', __('Payment received — your subscription is active.')],
            PaymentStatus::Failed => ['danger', $result->failureReason ?? __('The payment was not completed.')],
            default => ['warning', __('Your payment is still processing. This page will update once Paystack confirms it.')],
        });
    }
}
