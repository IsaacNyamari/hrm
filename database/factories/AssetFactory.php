<?php

namespace Database\Factories;

use App\Enums\AssetCategory;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A name always comes with the category it really belongs to.
        [$name, $category] = fake()->randomElement([
            ['MacBook Pro 14"', AssetCategory::Laptop],
            ['Dell Latitude', AssetCategory::Laptop],
            ['iPhone 15', AssetCategory::Phone],
            ['Dell 27" Monitor', AssetCategory::Monitor],
            ['Herman Miller Chair', AssetCategory::Furniture],
        ]);

        return [
            'tenant_id' => Tenant::factory(),
            'asset_tag' => 'AST-'.fake()->unique()->numerify('#####'),
            'name' => $name,
            'category' => $category,
            'serial_number' => strtoupper(fake()->bothify('SN-####-????')),
            'status' => AssetStatus::Available,
            'currency' => 'KES',
            'purchased_on' => now()->subMonths(6)->toDateString(),
            'purchase_cost_cents' => fake()->numberBetween(50, 3000) * 1000,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }
}
