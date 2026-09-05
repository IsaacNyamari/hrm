<?php

namespace Database\Factories;

use App\Enums\WebhookEvent;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'event' => WebhookEvent::EmployeeCreated->value,
            'payload' => ['uuid' => fake()->uuid()],
            'status' => 'pending',
        ];
    }

    public function forEndpoint(WebhookEndpoint $endpoint): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $endpoint->tenant_id,
            'webhook_endpoint_id' => $endpoint->getKey(),
        ]);
    }
}
