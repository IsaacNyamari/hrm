<?php

namespace Database\Factories;

use App\Models\DocumentType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Fire Certificate', 'Safety Permit', 'Training Record', 'Health Card', 'Insurance Cover']);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'code' => Str::upper(Str::slug($name, '_')).'_'.fake()->unique()->numerify('###'),
            'requires_expiry' => true,
            'notify_before_days' => [30, 14, 7],
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }
}
