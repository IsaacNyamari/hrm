<?php

namespace Database\Factories;

use App\Enums\LeaveAccrual;
use App\Models\LeaveType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Annual Leave', 'Sick Leave', 'Study Leave', 'Compassionate Leave', 'Unpaid Leave',
        ]);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'code' => Str::upper(Str::slug($name, '_')),
            'color' => fake()->hexColor(),
            'default_days_per_year' => 21,
            'accrual' => LeaveAccrual::Annual,
            'is_paid' => true,
            'requires_approval' => true,
            'requires_attachment' => false,
            'min_notice_days' => 0,
            'max_consecutive_days' => null,
            'allow_carry_forward' => false,
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function autoApproved(): static
    {
        return $this->state(fn (): array => ['requires_approval' => false]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (): array => ['is_paid' => false, 'default_days_per_year' => 0]);
    }
}
