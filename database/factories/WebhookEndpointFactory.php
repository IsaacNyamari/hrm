<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company().' hook',
            'url' => 'https://example.test/hooks/'.fake()->uuid(),
            'secret' => Str::random(40),
            'events' => ['*'],
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    /**
     * @param  array<int, string>  $events
     */
    public function subscribedTo(array $events): static
    {
        return $this->state(fn (): array => ['events' => $events]);
    }
}
