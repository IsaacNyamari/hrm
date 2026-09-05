<?php

namespace Database\Factories;

use App\Enums\AssetAssignmentStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetAssignment>
 */
class AssetAssignmentFactory extends Factory
{
    protected $model = AssetAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'asset_id' => Asset::factory(),
            'employee_id' => Employee::factory(),
            'assigned_on' => now()->subMonth()->toDateString(),
            'status' => AssetAssignmentStatus::Active,
            'condition_out' => 'Good',
        ];
    }

    public function forAsset(Asset $asset): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $asset->tenant_id,
            'asset_id' => $asset->getKey(),
        ]);
    }
}
