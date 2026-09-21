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
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Safaricom Daraja (M-Pesa) — Lipa na M-Pesa Online / STK push.
 *
 * Only the pieces the billing flow needs are implemented. Amounts are handled
 * in cents internally and converted to whole KES for Safaricom (which does not
 * accept fractional amounts).
 */
class MpesaGateway implements PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Mpesa;
    }

    public function initiatePayment(PaymentRequest $request): PaymentResult
    {
        $timestamp = now()->format('YmdHis');
        $shortcode = (string) $this->config['shortcode'];
        $password = base64_encode($shortcode.$this->config['passkey'].$timestamp);

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post($this->url('/mpesa/stkpush/v1/processrequest'), [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => intdiv($request->amountCents, 100),
                'PartyA' => $request->payer,
                'PartyB' => $shortcode,
                'PhoneNumber' => $request->payer,
                'CallBackURL' => $this->callbackUrl(),
                'AccountReference' => $request->reference,
                'TransactionDesc' => $request->description ?? 'Subscription payment',
            ]);

        $body = $response->json();

        if (! $response->successful() || ($body['ResponseCode'] ?? '1') !== '0') {
            return new PaymentResult(
                status: PaymentStatus::Failed,
                reference: $request->reference,
                failureReason: $body['errorMessage'] ?? $body['ResponseDescription'] ?? 'STK push rejected',
                raw: is_array($body) ? $body : [],
            );
        }

        return new PaymentResult(
            status: PaymentStatus::Pending,
            reference: $request->reference,
            externalId: $body['CheckoutRequestID'] ?? null,
            amountCents: $request->amountCents,
            raw: $body,
        );
    }

    public function verifyPayment(string $reference): PaymentResult
    {
        // Daraja query is keyed by CheckoutRequestID; callers that only hold our
        // reference should look the external id up on the Payment first.
        return $this->queryTransaction($reference);
    }

    public function queryTransaction(string $externalId): PaymentResult
    {
        $timestamp = now()->format('YmdHis');
        $shortcode = (string) $this->config['shortcode'];
        $password = base64_encode($shortcode.$this->config['passkey'].$timestamp);

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post($this->url('/mpesa/stkpushquery/v1/query'), [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $externalId,
            ]);

        $body = $response->json();
        $resultCode = (string) ($body['ResultCode'] ?? '1');

        return new PaymentResult(
            status: $resultCode === '0' ? PaymentStatus::Succeeded : ($resultCode === '1032' ? PaymentStatus::Failed : PaymentStatus::Pending),
            reference: $externalId,
            externalId: $externalId,
            failureReason: $resultCode === '0' ? null : ($body['ResultDesc'] ?? null),
            raw: is_array($body) ? $body : [],
        );
    }

    public function refund(Payment $payment, ?int $amountCents = null): PaymentResult
    {
        // Daraja reversal API — out of scope for this phase.
        throw new RuntimeException('M-Pesa reversal is not implemented yet.');
    }

    public function handleWebhook(Request $request): WebhookEvent
    {
        $verified = hash_equals(
            (string) ($this->config['callback_token'] ?? ''),
            (string) $request->query('token', ''),
        ) && $this->config['callback_token'] !== null;

        $callback = (array) $request->input('Body.stkCallback', []);
        $resultCode = (string) ($callback['ResultCode'] ?? '1');

        $items = (array) ($callback['CallbackMetadata']['Item'] ?? []);
        $meta = collect($items)->mapWithKeys(
            fn (mixed $item): array => is_array($item) ? [$item['Name'] => $item['Value'] ?? null] : [],
        );

        return new WebhookEvent(
            provider: PaymentProvider::Mpesa->value,
            status: $resultCode === '0' ? PaymentStatus::Succeeded : PaymentStatus::Failed,
            verified: $verified,
            reference: $request->input('Body.stkCallback.AccountReference') ?? $meta->get('AccountReference'),
            externalId: $callback['CheckoutRequestID'] ?? null,
            amountCents: $meta->has('Amount') ? (int) round(((float) $meta->get('Amount')) * 100) : null,
            currency: 'KES',
            failureReason: $resultCode === '0' ? null : ($callback['ResultDesc'] ?? null),
            payload: (array) $request->input('Body', []),
        );
    }

    private function accessToken(): string
    {
        if (blank($this->config['consumer_key'] ?? null) || blank($this->config['consumer_secret'] ?? null)) {
            throw new RuntimeException('M-Pesa is not configured: set MPESA_CONSUMER_KEY and MPESA_CONSUMER_SECRET.');
        }

        $response = Http::withBasicAuth(
            (string) $this->config['consumer_key'],
            (string) $this->config['consumer_secret'],
        )->acceptJson()->get($this->url('/oauth/v1/generate'), ['grant_type' => 'client_credentials']);

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Unable to obtain an M-Pesa access token (HTTP '.$response->status().').');
        }

        return $token;
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->config['base_url'], '/').$path;
    }

    private function callbackUrl(): string
    {
        $url = (string) $this->config['callback_url'];
        $token = $this->config['callback_token'];

        return $token ? $url.(str_contains($url, '?') ? '&' : '?').'token='.$token : $url;
    }
}
