<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1, 10) * 100000;

        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => null,
            'number' => 'INV-'.fake()->unique()->numerify('######'),
            'status' => InvoiceStatus::Open,
            'currency' => 'KES',
            'subtotal_cents' => $subtotal,
            'tax_cents' => 0,
            'total_cents' => $subtotal,
            'amount_paid_cents' => 0,
            'period_start' => now(),
            'period_end' => now()->addMonthNoOverflow(),
            'due_at' => now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Paid,
            'amount_paid_cents' => $attributes['total_cents'],
            'paid_at' => now(),
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }
}
