<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Facades\Tenancy;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = Tenancy::id();

        return [
            'employee_number' => [
                'nullable', 'string', 'max:50',
                Rule::unique('employees', 'employee_number')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('employees', 'email')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', new Enum(Gender::class)],
            'marital_status' => ['nullable', new Enum(MaritalStatus::class)],
            'nationality' => ['nullable', 'string', 'max:100'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'hire_date' => ['nullable', 'date'],
            'probation_ends_at' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'status' => ['nullable', new Enum(EmploymentStatus::class)],
            'employment_type_id' => ['nullable', $this->existsForTenant('employment_types')],
            'department_id' => ['nullable', $this->existsForTenant('departments')],
            'designation_id' => ['nullable', $this->existsForTenant('designations')],
            'branch_id' => ['nullable', $this->existsForTenant('branches')],
            'manager_id' => ['nullable', $this->existsForTenant('employees')],
            'salary_amount_cents' => ['nullable', 'integer', 'min:0'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'pay_frequency' => ['nullable', 'in:monthly,biweekly,weekly,daily,hourly'],
            'notes' => ['nullable', 'string', 'max:5000'],

            'emergency_contacts' => ['sometimes', 'array'],
            'emergency_contacts.*.name' => ['required_with:emergency_contacts', 'string', 'max:150'],
            'emergency_contacts.*.relationship' => ['nullable', 'string', 'max:60'],
            'emergency_contacts.*.phone' => ['required_with:emergency_contacts', 'string', 'max:30'],
            'emergency_contacts.*.is_primary' => ['boolean'],

            'bank_accounts' => ['sometimes', 'array'],
            'bank_accounts.*.bank_name' => ['required_with:bank_accounts', 'string', 'max:150'],
            'bank_accounts.*.account_name' => ['required_with:bank_accounts', 'string', 'max:150'],
            'bank_accounts.*.account_number' => ['required_with:bank_accounts', 'string', 'max:60'],
            'bank_accounts.*.is_primary' => ['boolean'],

            'statutory' => ['sometimes', 'array'],
            'statutory.national_id' => ['nullable', 'string', 'max:30'],
            'statutory.tax_pin' => ['nullable', 'string', 'max:60'],
            'statutory.nssf_number' => ['nullable', 'string', 'max:60'],
            'statutory.shif_number' => ['nullable', 'string', 'max:60'],
            'statutory.nhif_number' => ['nullable', 'string', 'max:60'],
        ];
    }

    private function existsForTenant(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('tenant_id', Tenancy::id());
    }
}
