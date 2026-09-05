<?php

namespace Database\Factories;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'invoice_id' => null,
            'provider' => PaymentProvider::Mpesa,
            'status' => PaymentStatus::Pending,
            'amount_cents' => fake()->numberBetween(1, 10) * 100000,
            'currency' => 'KES',
            'reference' => 'PAY-'.Str::upper(Str::random(20)),
            'payer' => '2547'.fake()->numerify('########'),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (): array => ['status' => PaymentStatus::Succeeded, 'paid_at' => now()]);
    }
}
