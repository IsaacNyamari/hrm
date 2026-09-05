<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\TenantContext;
use Illuminate\Support\Str;

class WebhookService
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Fan a domain event out to every active endpoint of the bound tenant that
     * is subscribed to it, queuing one delivery per endpoint.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(WebhookEvent $event, array $payload): int
    {
        if (! $this->context->has()) {
            return 0;
        }

        $endpoints = WebhookEndpoint::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => $endpoint->subscribesTo($event));

        $queued = 0;
        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::query()->create([
                'webhook_endpoint_id' => $endpoint->getKey(),
                'event' => $event->value,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            DeliverWebhook::dispatch($delivery->getKey());
            $queued++;
        }

        return $queued;
    }

    /**
     * Send a test ping to a single endpoint immediately (still via the queue).
     */
    public function ping(WebhookEndpoint $endpoint): WebhookDelivery
    {
        $delivery = WebhookDelivery::query()->create([
            'tenant_id' => $endpoint->tenant_id,
            'webhook_endpoint_id' => $endpoint->getKey(),
            'event' => WebhookEvent::Ping->value,
            'payload' => ['message' => 'This is a test event from your HRM.', 'nonce' => (string) Str::uuid()],
            'status' => 'pending',
        ]);

        DeliverWebhook::dispatch($delivery->getKey());

        return $delivery;
    }
}
