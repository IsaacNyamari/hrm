<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\Payment\PaymentManager;
use App\Support\Payment\PaymentRequest;
use App\Support\Payment\PaymentResult;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\HttpClientException;
use RuntimeException;

/**
 * Kicks off a charge with the provider for a pending Payment and records the
 * provider's response. The payment only becomes "succeeded" later, via a
 * verified webhook processed by PaymentReconciler.
 *
 * A provider that cannot be reached (bad credentials, network failure) marks
 * the payment failed with a user-safe reason instead of bubbling a 500; the
 * underlying exception is reported for operators.
 */
class ChargeInvoice
{
    public function __construct(private readonly PaymentManager $payments) {}

    public function handle(Payment $payment): PaymentResult
    {
        $gateway = $this->payments->gateway($payment->provider);

        try {
            $result = $gateway->initiatePayment(new PaymentRequest(
                amountCents: $payment->amount_cents,
                currency: $payment->currency,
                reference: $payment->reference,
                payer: $payment->payer,
                description: 'Subscription',
            ));
        } catch (HttpClientException|RuntimeException $exception) {
            report($exception);

            $result = new PaymentResult(
                status: PaymentStatus::Failed,
                reference: $payment->reference,
                failureReason: __('The payment provider is unavailable right now. Please try again later.'),
                raw: ['error' => $exception->getMessage()],
            );
        }

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
