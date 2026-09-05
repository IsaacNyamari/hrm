<?php

namespace Database\Factories;

use App\Enums\CalculationMethod;
use App\Enums\SalaryComponentType;
use App\Models\SalaryComponent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SalaryComponent>
 */
class SalaryComponentFactory extends Factory
{
    protected $model = SalaryComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Airtime Allowance', 'Lunch Allowance', 'Risk Allowance', 'Acting Allowance']);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'code' => Str::upper(Str::slug($name, '_')),
            'type' => SalaryComponentType::Earning,
            'calculation_method' => CalculationMethod::Fixed,
            'amount_cents' => 500000,
            'is_taxable' => true,
            'is_pensionable' => false,
            'is_statutory' => false,
            'is_recurring' => true,
            'sort_order' => 50,
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function percentage(float $rate, string $base = 'BASIC'): static
    {
        return $this->state(fn (): array => [
            'calculation_method' => CalculationMethod::Percentage,
            'amount_cents' => null,
            'rate' => $rate,
            'base_code' => $base,
        ]);
    }

    public function formula(string $formula): static
    {
        return $this->state(fn (): array => [
            'calculation_method' => CalculationMethod::Formula,
            'amount_cents' => null,
            'formula' => $formula,
        ]);
    }

    public function deduction(): static
    {
        return $this->state(fn (): array => ['type' => SalaryComponentType::Deduction, 'is_taxable' => false]);
    }
}
