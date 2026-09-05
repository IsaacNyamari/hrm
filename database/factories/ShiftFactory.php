<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Day Shift',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_minutes' => 60,
            'grace_minutes' => 10,
            'working_days' => [1, 2, 3, 4, 5],
            'overtime_after_minutes' => 30,
            'is_night_shift' => false,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }

    public function night(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Night Shift',
            'start_time' => '20:00',
            'end_time' => '05:00',
            'is_night_shift' => true,
        ]);
    }
}
