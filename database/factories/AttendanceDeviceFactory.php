<?php

namespace Database\Factories;

use App\Enums\AttendanceDeviceType;
use App\Models\AttendanceDevice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttendanceDevice>
 */
class AttendanceDeviceFactory extends Factory
{
    protected $model = AttendanceDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Main Gate', 'Reception', 'Warehouse Door', 'Back Entrance']),
            'type' => AttendanceDeviceType::Fingerprint,
            'serial_number' => 'ZK-'.fake()->unique()->numerify('#####'),
            'secret' => Str::random(48),
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }
}
