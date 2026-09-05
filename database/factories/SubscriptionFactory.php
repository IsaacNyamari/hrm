<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = now();

        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'starts_at' => $now,
            'trial_ends_at' => null,
            'current_period_start' => $now,
            'current_period_end' => $now->copy()->addMonthNoOverflow(),
        ];
    }

    public function trialing(int $daysLeft = 14): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays($daysLeft),
            'current_period_end' => now()->addDays($daysLeft),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::PastDue,
            'current_period_end' => now()->subDays(1),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Expired,
            'current_period_end' => now()->subMonth(),
            'ends_at' => now()->subDays(10),
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function onPlan(Plan $plan): static
    {
        return $this->state(fn (): array => ['plan_id' => $plan->getKey()]);
    }
}
