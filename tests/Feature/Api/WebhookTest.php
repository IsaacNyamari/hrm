<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\WebhookEvent;
use App\Events\EmployeeCreated;
use App\Jobs\DeliverWebhook;
use App\Models\Employee;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'hook-co', 'name' => 'Hook Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->admin = userForTenant($this->tenant, Role::TenantOwner); // webhooks.manage
});

it('lets an admin register a webhook endpoint', function () {
    $this->actingAs($this->admin)
        ->postJson('http://hook-co.hrm.test/webhook-endpoints', [
            'name' => 'CRM sync', 'url' => 'https://crm.example/hooks', 'events' => ['employee.created'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonStructure(['data' => ['uuid', 'secret']]);
});

it('forbids webhook management without the permission', function () {
    $staff = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($staff)
        ->getJson('http://hook-co.hrm.test/webhook-endpoints')
        ->assertForbidden();
});

it('queues a delivery when a subscribed event fires', function () {
    Queue::fake();

    WebhookEndpoint::factory()->forTenant($this->tenant)->subscribedTo(['employee.created'])->create();
    WebhookEndpoint::factory()->forTenant($this->tenant)->subscribedTo(['leave.approved'])->create(); // not subscribed
    WebhookEndpoint::factory()->forTenant($this->tenant)->subscribedTo(['employee.created'])->create(['is_active' => false]);

    app(WebhookService::class)->dispatch(WebhookEvent::EmployeeCreated, ['uuid' => 'abc']);

    expect(WebhookDelivery::query()->count())->toBe(1);
    Queue::assertPushed(DeliverWebhook::class, 1);
});

it('delivers a signed payload and marks success', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $endpoint = WebhookEndpoint::factory()->forTenant($this->tenant)->create(['failure_count' => 3]);
    $delivery = WebhookDelivery::factory()->forEndpoint($endpoint)->create(['event' => 'webhook.ping']);

    (new DeliverWebhook($delivery->id))->handle(app(TenantContext::class));

    expect($delivery->fresh()->status)->toBe('success')
        ->and($endpoint->fresh()->failure_count)->toBe(0)
        ->and($endpoint->fresh()->last_success_at)->not->toBeNull();

    Http::assertSent(function ($request) {
        return str_starts_with((string) $request->header('X-Webhook-Signature')[0], 'sha256=')
            && $request->header('X-Webhook-Event')[0] === 'webhook.ping';
    });
});

it('counts failures and disables an endpoint after the threshold', function () {
    Http::fake(['*' => Http::response('nope', 500)]);

    $endpoint = WebhookEndpoint::factory()->forTenant($this->tenant)->create(['failure_count' => 9]);
    $delivery = WebhookDelivery::factory()->forEndpoint($endpoint)->create();

    try {
        (new DeliverWebhook($delivery->id))->handle(app(TenantContext::class));
    } catch (RuntimeException) {
        // expected — the job rethrows so the queue can retry
    }

    expect($endpoint->fresh()->failure_count)->toBe(10)
        ->and($endpoint->fresh()->is_active)->toBeFalse()
        ->and($delivery->fresh()->attempts)->toBe(1);
});

it('pings an endpoint on demand', function () {
    Queue::fake();
    $endpoint = WebhookEndpoint::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->admin)
        ->postJson("http://hook-co.hrm.test/webhook-endpoints/{$endpoint->uuid}/ping")
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'pending');

    Queue::assertPushed(DeliverWebhook::class);
});

it('fires a real webhook when the employee-created event dispatches', function () {
    Queue::fake();
    WebhookEndpoint::factory()->forTenant($this->tenant)->subscribedTo(['employee.created'])->create();

    EmployeeCreated::dispatch(Employee::factory()->forTenant($this->tenant)->create());

    Queue::assertPushed(DeliverWebhook::class);
});
