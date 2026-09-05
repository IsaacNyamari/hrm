<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'category' => DocumentCategory::Certificate,
            'title' => fake()->randomElement(['Degree Certificate', 'ID Copy', 'Contract 2026', 'CV']),
            'original_filename' => 'document.pdf',
            'disk' => 'local',
            'path' => 'tenants/x/employees/y/documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(10_000, 2_000_000),
            'is_confidential' => false,
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->getKey(),
        ]);
    }

    public function expiringIn(int $days): static
    {
        return $this->state(fn (): array => ['expiry_date' => now()->addDays($days)->toDateString()]);
    }
}
