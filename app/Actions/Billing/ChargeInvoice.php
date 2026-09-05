<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\Payment\PaymentManager;
use App\Support\Payment\PaymentRequest;
use App\Support\Payment\PaymentResult;
use Carbon\CarbonImmutable;

/**
 * Kicks off a charge with the provider for a pending Payment and records the
 * provider's response. The payment only becomes "succeeded" later, via a
 * verified webhook processed by PaymentReconciler.
 */
class ChargeInvoice
{
    public function __construct(private readonly PaymentManager $payments) {}

    public function handle(Payment $payment): PaymentResult
    {
        $gateway = $this->payments->gateway($payment->provider);

        $result = $gateway->initiatePayment(new PaymentRequest(
            amountCents: $payment->amount_cents,
            currency: $payment->currency,
            reference: $payment->reference,
            payer: $payment->payer,
            description: 'Subscription',
        ));

        $payment->forceFill([
            'provider_reference' => $result->externalId ?? $payment->provider_reference,
            'status' => $result->status,
            'failure_reason' => $result->failureReason,
        ])->save();

        PaymentTransaction::create([
            'payment_id' => $payment->getKey(),
            'tenant_id' => $payment->tenant_id,
            'provider' => $payment->provider->value,
            'type' => 'charge',
            'status' => $result->status->value,
            'external_id' => null, // reserved for the webhook row (unique per provider)
            'amount_cents' => $payment->amount_cents,
            'currency' => $payment->currency,
            'payload' => $result->raw + ['provider_reference' => $result->externalId],
            'processed_at' => CarbonImmutable::now(),
        ]);

        return $result;
    }
}
