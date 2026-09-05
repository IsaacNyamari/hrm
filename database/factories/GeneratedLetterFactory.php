<?php

namespace Database\Factories;

use App\Enums\DocumentTemplateType;
use App\Models\Employee;
use App\Models\GeneratedLetter;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneratedLetter>
 */
class GeneratedLetterFactory extends Factory
{
    protected $model = GeneratedLetter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'type' => DocumentTemplateType::EmploymentLetter,
            'subject' => 'Confirmation of Employment',
            'body' => 'This confirms employment.',
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->getKey(),
        ]);
    }
}
