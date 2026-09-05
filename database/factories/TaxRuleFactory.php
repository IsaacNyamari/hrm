<?php

namespace Database\Factories;

use App\Enums\TaxRuleType;
use App\Models\TaxRule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRule>
 */
class TaxRuleFactory extends Factory
{
    protected $model = TaxRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'SHIF (Kenya)',
            'type' => TaxRuleType::Shif,
            'country' => 'KE',
            'effective_from' => '2024-07-01',
            'effective_to' => null,
            'config' => ['rate' => 0.0275, 'minimum_cents' => 30000],
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function of(TaxRuleType $type, array $config): static
    {
        return $this->state(fn (): array => ['type' => $type, 'name' => $type->label(), 'config' => $config]);
    }
}
