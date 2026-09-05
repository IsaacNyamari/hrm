<?php

namespace Database\Factories;

use App\Enums\DocumentTemplateType;
use App\Models\DocumentTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
{
    protected $model = DocumentTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->randomElement(['Employment Letter', 'Reference Letter', 'Warning Letter']),
            'type' => DocumentTemplateType::EmploymentLetter,
            'subject' => 'Confirmation of Employment',
            'body' => 'This confirms that {{ employee.name }} ({{ employee.number }}) works at {{ company.name }} as {{ employee.position }} since {{ employee.hire_date }}. Issued {{ date }}.',
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }
}
