<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WebhookEvent;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookEndpointController extends Controller
{
    public function __construct(private readonly WebhookService $webhooks) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('webhooks.manage'), 403);

        return response()->json([
            'data' => WebhookEndpoint::query()->latest('id')->get()->map(fn (WebhookEndpoint $e): array => $this->transform($e)),
            'meta' => ['available_events' => WebhookEvent::subscribable()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->can('webhooks.manage'), 403);

        $data = $this->validated($request);

        $endpoint = WebhookEndpoint::query()->create([
            'name' => $data['name'],
            'url' => $data['url'],
            'secret' => $data['secret'] ?? Str::random(40),
            'events' => $data['events'] ?? ['*'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->getKey(),
        ]);

        return response()->json(['data' => $this->transform($endpoint->fresh(), withSecret: true)], 201);
    }

    public function update(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        abort_unless($request->user()?->can('webhooks.manage'), 403);

        $data = $this->validated($request, $webhookEndpoint);
        $attributes = array_filter([
            'name' => $data['name'] ?? null,
            'url' => $data['url'] ?? null,
            'events' => $data['events'] ?? null,
            'description' => $data['description'] ?? null,
        ], static fn ($v): bool => $v !== null);

        if (array_key_exists('is_active', $data)) {
            $attributes['is_active'] = (bool) $data['is_active'];
            if ($attributes['is_active']) {
                $attributes['failure_count'] = 0;
                $attributes['disabled_at'] = null;
            }
        }
        if (! empty($data['secret'])) {
            $attributes['secret'] = $data['secret'];
        }

        $webhookEndpoint->forceFill($attributes)->save();

        return response()->json(['data' => $this->transform($webhookEndpoint->fresh())]);
    }

    public function destroy(Request $request, WebhookEndpoint $webhookEndpoint): Response
    {
        abort_unless($request->user()?->can('webhooks.manage'), 403);

        $webhookEndpoint->delete();

        return response()->noContent();
    }

    public function ping(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        abort_unless($request->user()?->can('webhooks.manage'), 403);

        $delivery = $this->webhooks->ping($webhookEndpoint);

        return response()->json(['data' => ['delivery_uuid' => $delivery->uuid, 'status' => $delivery->status]], 202);
    }

    public function deliveries(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        abort_unless($request->user()?->can('webhooks.manage'), 403);

        return response()->json([
            'data' => $webhookEndpoint->deliveries()->limit(50)->get()->map(fn ($d): array => [
                'uuid' => $d->uuid,
                'event' => $d->event,
                'status' => $d->status,
                'attempts' => $d->attempts,
                'response_status' => $d->response_status,
                'delivered_at' => $d->delivered_at?->toIso8601String(),
                'created_at' => $d->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?WebhookEndpoint $endpoint = null): array
    {
        $required = $endpoint === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'url' => [$required, 'url', 'max:500'],
            'secret' => ['nullable', 'string', 'min:16', 'max:200'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(array_merge(['*'], WebhookEvent::subscribable()))],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(WebhookEndpoint $endpoint, bool $withSecret = false): array
    {
        $data = [
            'uuid' => $endpoint->uuid,
            'name' => $endpoint->name,
            'url' => $endpoint->url,
            'events' => $endpoint->events,
            'is_active' => $endpoint->is_active,
            'description' => $endpoint->description,
            'failure_count' => $endpoint->failure_count,
            'last_success_at' => $endpoint->last_success_at?->toIso8601String(),
            'last_failure_at' => $endpoint->last_failure_at?->toIso8601String(),
        ];

        if ($withSecret) {
            $data['secret'] = $endpoint->secret;
        }

        return $data;
    }
}
