<?php

namespace Database\Factories;

use App\Enums\HolidayType;
use App\Models\Holiday;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->randomElement([
                'New Year\'s Day', 'Labour Day', 'Madaraka Day', 'Mashujaa Day',
                'Jamhuri Day', 'Christmas Day', 'Boxing Day',
            ]),
            'date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'type' => HolidayType::Public,
            'is_recurring' => false,
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function on(string $date): static
    {
        return $this->state(fn (): array => ['date' => $date]);
    }
}
