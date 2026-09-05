<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Payment\PaymentRequest;
use App\Support\Payment\PaymentResult;
use App\Support\Payment\WebhookEvent;
use Illuminate\Http\Request;

/**
 * Offline settlement (bank transfer, cash, a platform admin marking an invoice
 * paid). No external calls — a payment stays pending until an operator confirms
 * it through the billing UI.
 */
class ManualGateway implements PaymentGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Manual;
    }

    public function initiatePayment(PaymentRequest $request): PaymentResult
    {
        return new PaymentResult(
            status: PaymentStatus::Pending,
            reference: $request->reference,
            amountCents: $request->amountCents,
        );
    }

    public function verifyPayment(string $reference): PaymentResult
    {
        return new PaymentResult(status: PaymentStatus::Pending, reference: $reference);
    }

    public function queryTransaction(string $externalId): PaymentResult
    {
        return new PaymentResult(status: PaymentStatus::Pending, reference: $externalId);
    }

    public function refund(Payment $payment, ?int $amountCents = null): PaymentResult
    {
        return new PaymentResult(
            status: PaymentStatus::Refunded,
            reference: $payment->reference,
            amountCents: $amountCents ?? $payment->amount_cents,
        );
    }

    public function handleWebhook(Request $request): WebhookEvent
    {
        return new WebhookEvent(
            provider: PaymentProvider::Manual->value,
            status: PaymentStatus::Pending,
            verified: false,
        );
    }
}
