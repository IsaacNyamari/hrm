<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $tries = 5;

    /** Disable this endpoint after this many consecutive failed deliveries. */
    private const DISABLE_AFTER = 10;

    public function __construct(private readonly int $deliveryId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(TenantContext $context): void
    {
        $delivery = WebhookDelivery::withoutTenantScope()->with('endpoint')->find($this->deliveryId);

        if ($delivery === null || $delivery->status === 'success') {
            return;
        }

        $endpoint = $delivery->endpoint;
        $tenant = $endpoint->tenant()->first();

        if ($tenant === null) {
            return;
        }

        $context->run($tenant, function () use ($delivery, $endpoint): void {
            $body = json_encode([
                'id' => $delivery->uuid,
                'event' => $delivery->event,
                'created_at' => CarbonImmutable::now()->toIso8601String(),
                'data' => $delivery->payload,
            ], JSON_THROW_ON_ERROR);

            $timestamp = (string) CarbonImmutable::now()->getTimestamp();
            $signature = hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret);

            $delivery->increment('attempts');

            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'HRM-Webhooks/1',
                    'X-Webhook-Id' => $delivery->uuid,
                    'X-Webhook-Event' => $delivery->event,
                    'X-Webhook-Timestamp' => $timestamp,
                    'X-Webhook-Signature' => 'sha256='.$signature,
                ])->timeout(10)->connectTimeout(5)->withBody($body, 'application/json')->post($endpoint->url);
            } catch (Throwable $e) {
                $this->recordFailure($delivery, $endpoint, null, Str::limit($e->getMessage(), 500));

                throw $e;
            }

            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => 'success',
                    'response_status' => $response->status(),
                    'response_body' => Str::limit($response->body(), 2000),
                    'delivered_at' => CarbonImmutable::now(),
                ])->save();

                $endpoint->forceFill([
                    'failure_count' => 0,
                    'last_success_at' => CarbonImmutable::now(),
                ])->save();

                return;
            }

            $this->recordFailure($delivery, $endpoint, $response->status(), Str::limit($response->body(), 2000));

            throw new \RuntimeException("Webhook endpoint responded {$response->status()}.");
        });
    }

    public function failed(Throwable $exception): void
    {
        $delivery = WebhookDelivery::withoutTenantScope()->find($this->deliveryId);

        if ($delivery !== null && $delivery->status !== 'success') {
            $delivery->forceFill(['status' => 'failed', 'failed_at' => CarbonImmutable::now()])->save();
        }
    }

    private function recordFailure(WebhookDelivery $delivery, WebhookEndpoint $endpoint, ?int $status, string $body): void
    {
        $delivery->forceFill([
            'response_status' => $status,
            'response_body' => $body,
        ])->save();

        $failures = $endpoint->failure_count + 1;
        $attributes = ['failure_count' => $failures, 'last_failure_at' => CarbonImmutable::now()];

        if ($failures >= self::DISABLE_AFTER) {
            $attributes['is_active'] = false;
            $attributes['disabled_at'] = CarbonImmutable::now();
        }

        $endpoint->forceFill($attributes)->save();
    }
}
