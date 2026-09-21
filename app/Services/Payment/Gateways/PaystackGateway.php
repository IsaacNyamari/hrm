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
use Binkode\Paystack\Support\Refund;
use Binkode\Paystack\Support\Transaction;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Paystack hosted checkout (card, M-Pesa, bank — whatever the merchant account
 * enables), via binkode/laravel-paystack.
 *
 * The payer is redirected to Paystack's page and returned to
 * `billing.paystack.callback`, where the charge is verified server-side; a
 * signed `charge.success` webhook confirms it independently. Both carry the
 * Paystack transaction id, so the reconciler applies whichever arrives first
 * exactly once. Amounts are sent in the currency's subunit (cents).
 */
class PaystackGateway implements PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Paystack;
    }

    public function initiatePayment(PaymentRequest $request): PaymentResult
    {
        $this->ensureConfigured();

        try {
            $response = Transaction::initialize([
                'email' => $request->payer,
                'amount' => $request->amountCents,
                'currency' => $request->currency,
                'reference' => $request->reference,
                'callback_url' => route('billing.paystack.callback'),
                'metadata' => [...$request->metadata, 'description' => $request->description],
            ]);
        } catch (HttpException $exception) {
            return new PaymentResult(
                status: PaymentStatus::Failed,
                reference: $request->reference,
                failureReason: $exception->getMessage() !== '' ? $exception->getMessage() : 'Paystack rejected the payment.',
            );
        }

        $data = (array) ($response['data'] ?? []);

        // The Paystack transaction id only exists once the payer completes
        // checkout; it reaches us through the callback / webhook.
        return new PaymentResult(
            status: PaymentStatus::Pending,
            reference: $request->reference,
            amountCents: $request->amountCents,
            raw: $data,
            redirectUrl: $data['authorization_url'] ?? null,
        );
    }

    public function verifyPayment(string $reference): PaymentResult
    {
        $this->ensureConfigured();

        return $this->resultFromTransaction((array) (Transaction::verify(rawurlencode($reference))['data'] ?? []), $reference);
    }

    public function queryTransaction(string $externalId): PaymentResult
    {
        $this->ensureConfigured();

        return $this->resultFromTransaction((array) (Transaction::fetch(rawurlencode($externalId))['data'] ?? []), $externalId);
    }

    public function refund(Payment $payment, ?int $amountCents = null): PaymentResult
    {
        $this->ensureConfigured();

        $response = Refund::create(array_filter([
            'transaction' => $payment->reference,
            'amount' => $amountCents,
        ]));

        return new PaymentResult(
            status: PaymentStatus::Refunded,
            reference: $payment->reference,
            externalId: isset($response['data']['id']) ? (string) $response['data']['id'] : null,
            amountCents: $amountCents ?? $payment->amount_cents,
            raw: (array) ($response['data'] ?? []),
        );
    }

    /**
     * Paystack signs the raw body with HMAC-SHA512 using the secret key. Only
     * charge outcomes produce a settled/failed status; every other event type
     * comes back Pending so the caller can acknowledge and ignore it.
     */
    public function handleWebhook(Request $request): WebhookEvent
    {
        $secret = (string) ($this->config['secret_key'] ?? '');
        $signature = (string) $request->header('x-paystack-signature', '');

        $verified = $secret !== ''
            && $signature !== ''
            && hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature);

        $data = (array) $request->input('data', []);

        $status = match ($request->input('event')) {
            'charge.success' => PaymentStatus::Succeeded,
            'charge.failed' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        return new WebhookEvent(
            provider: PaymentProvider::Paystack->value,
            status: $status,
            verified: $verified,
            reference: $data['reference'] ?? null,
            externalId: isset($data['id']) ? (string) $data['id'] : null,
            amountCents: isset($data['amount']) ? (int) $data['amount'] : null,
            currency: $data['currency'] ?? null,
            failureReason: $status === PaymentStatus::Failed ? ($data['gateway_response'] ?? null) : null,
            payload: $data,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resultFromTransaction(array $data, string $reference): PaymentResult
    {
        $status = match ($data['status'] ?? null) {
            'success' => PaymentStatus::Succeeded,
            'failed', 'abandoned', 'reversed' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        return new PaymentResult(
            status: $status,
            reference: (string) ($data['reference'] ?? $reference),
            externalId: isset($data['id']) ? (string) $data['id'] : null,
            amountCents: isset($data['amount']) ? (int) $data['amount'] : null,
            failureReason: $status === PaymentStatus::Failed ? ($data['gateway_response'] ?? null) : null,
            raw: $data,
        );
    }

    private function ensureConfigured(): void
    {
        if (blank($this->config['secret_key'] ?? null)) {
            throw new RuntimeException('Paystack is not configured: set PAYSTACK_SECRET_KEY.');
        }
    }
}
