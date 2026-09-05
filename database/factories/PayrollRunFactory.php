<?php

namespace Database\Factories;

use App\Enums\PayrollRunStatus;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'payroll_period_id' => PayrollPeriod::factory(),
            'status' => PayrollRunStatus::Draft,
        ];
    }
}
