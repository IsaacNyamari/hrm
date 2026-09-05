<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\PaymentProvider;
use App\Models\Payment;
use App\Support\Payment\PaymentRequest;
use App\Support\Payment\PaymentResult;
use App\Support\Payment\WebhookEvent;
use Illuminate\Http\Request;

/**
 * A payment provider. The billing system depends only on this interface — never
 * on a concrete provider. Success is confirmed exclusively server-side via
 * {@see self::verifyPayment()} or a verified {@see self::handleWebhook()} event.
 */
interface PaymentGateway
{
    public function provider(): PaymentProvider;

    /**
     * Begin a charge (e.g. M-Pesa STK push). Returns a pending result carrying
     * the provider's transaction id.
     */
    public function initiatePayment(PaymentRequest $request): PaymentResult;

    /**
     * Ask the provider for the authoritative status of a charge we started.
     */
    public function verifyPayment(string $reference): PaymentResult;

    public function queryTransaction(string $externalId): PaymentResult;

    public function refund(Payment $payment, ?int $amountCents = null): PaymentResult;

    /**
     * Parse and verify an inbound provider callback. `verified` MUST be false
     * when authenticity cannot be established.
     */
    public function handleWebhook(Request $request): WebhookEvent;
}
