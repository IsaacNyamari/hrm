<?php

namespace Database\Factories;

use App\Enums\PayrollFrequency;
use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    protected $model = PayrollPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = CarbonImmutable::create(2026, fake()->numberBetween(1, 12), 1);
        $end = $start->endOfMonth();

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $start->format('F Y'),
            'frequency' => PayrollFrequency::Monthly,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'pay_date' => $end->toDateString(),
            'status' => PayrollPeriodStatus::Draft,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function forMonth(int $year, int $month): static
    {
        $start = CarbonImmutable::create($year, $month, 1);

        return $this->state(fn (): array => [
            'name' => $start->format('F Y'),
            'start_date' => $start->toDateString(),
            'end_date' => $start->endOfMonth()->toDateString(),
            'pay_date' => $start->endOfMonth()->toDateString(),
        ]);
    }
}
