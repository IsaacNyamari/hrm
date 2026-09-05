<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantBranding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantBranding>
 */
class TenantBrandingFactory extends Factory
{
    protected $model = TenantBranding::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'brand_name' => fake()->company(),
            'primary_color' => fake()->hexColor(),
            'from_name' => fake()->company().' HR',
            'from_email' => 'hr@'.fake()->unique()->domainName(),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }
}
