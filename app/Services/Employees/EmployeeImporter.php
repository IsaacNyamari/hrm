<?php

declare(strict_types=1);

namespace App\Services\Employees;

use App\Actions\Employees\CreateEmployee;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Exceptions\PlanLimitExceededException;
use App\Imports\EmployeeSheetImport;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeImport;
use App\Models\EmploymentType;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeImporter
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CreateEmployee $createEmployee,
    ) {}

    /**
     * Validate a file without writing anything.
     *
     * @return array{total: int, valid: int, invalid: int, sample: array<int, array<string, mixed>>, errors: array<int, array{row: int, messages: array<int, string>}>}
     */
    public function preview(EmployeeImport $import): array
    {
        return $this->context->run($import->tenant, function () use ($import): array {
            $rows = $this->readRows($import->storage_path);
            $errors = [];
            $sample = [];
            $valid = 0;

            foreach ($rows as $index => $row) {
                $mapped = $this->mapRow($row);
                $rowErrors = $this->validateRow($mapped);

                if ($rowErrors === []) {
                    $valid++;
                } else {
                    $errors[] = ['row' => $index + 2, 'messages' => $rowErrors];
                }

                if (count($sample) < 20) {
                    $sample[] = $mapped + ['_row' => $index + 2, '_valid' => $rowErrors === []];
                }
            }

            return [
                'total' => count($rows),
                'valid' => $valid,
                'invalid' => count($errors),
                'sample' => $sample,
                'errors' => $errors,
            ];
        });
    }

    /**
     * Create employees from a previously uploaded file, recording a report on
     * the EmployeeImport row.
     */
    public function run(EmployeeImport $import): void
    {
        $this->context->run($import->tenant, function () use ($import): void {
            $import->forceFill([
                'status' => EmployeeImport::STATUS_PROCESSING,
                'started_at' => CarbonImmutable::now(),
            ])->save();

            $rows = $this->readRows($import->storage_path);
            $errors = [];
            $imported = 0;
            $failed = 0;

            foreach ($rows as $index => $row) {
                $lineNumber = $index + 2;
                $mapped = $this->mapRow($row);
                $rowErrors = $this->validateRow($mapped);

                if ($rowErrors !== []) {
                    $failed++;
                    $errors[] = ['row' => $lineNumber, 'messages' => $rowErrors];

                    continue;
                }

                try {
                    $this->createEmployee->handle($import->tenant, $this->toAttributes($mapped));
                    $imported++;
                } catch (PlanLimitExceededException $e) {
                    $failed++;
                    $errors[] = ['row' => $lineNumber, 'messages' => [$e->getMessage().' Import stopped.']];
                    break;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = ['row' => $lineNumber, 'messages' => [$e->getMessage()]];
                }
            }

            $import->forceFill([
                'status' => EmployeeImport::STATUS_COMPLETED,
                'total_rows' => count($rows),
                'imported_rows' => $imported,
                'failed_rows' => $failed,
                'errors' => $errors,
                'completed_at' => CarbonImmutable::now(),
            ])->save();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRows(string $path): array
    {
        $sheet = new EmployeeSheetImport;
        Excel::import($sheet, $path, 'local');

        return array_values(array_filter(
            $sheet->rows,
            static fn (array $row): bool => trim(implode('', array_map('strval', $row))) !== '',
        ));
    }

    /**
     * Normalise a raw heading-keyed row.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        $get = static fn (string $key): ?string => isset($row[$key]) && $row[$key] !== '' ? trim((string) $row[$key]) : null;

        return [
            'employee_number' => $get('employee_number'),
            'first_name' => $get('first_name'),
            'middle_name' => $get('middle_name'),
            'last_name' => $get('last_name'),
            'preferred_name' => $get('preferred_name'),
            'email' => $get('email') !== null ? mb_strtolower($get('email')) : null,
            'personal_email' => $get('personal_email'),
            'phone' => $get('phone'),
            'date_of_birth' => $this->date($get('date_of_birth')),
            'gender' => $this->enumValue(Gender::class, $get('gender')),
            'marital_status' => $this->enumValue(MaritalStatus::class, $get('marital_status')),
            'nationality' => $get('nationality'),
            'job_title' => $get('job_title'),
            'hire_date' => $this->date($get('hire_date')),
            'employment_type' => $get('employment_type'),
            'department' => $get('department'),
            'designation' => $get('designation'),
            'branch' => $get('branch'),
            'salary_amount' => $get('salary_amount'),
            'pay_frequency' => $get('pay_frequency'),
            'tax_pin' => $get('tax_pin'),
            'nssf_number' => $get('nssf_number'),
            'shif_number' => $get('shif_number'),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function validateRow(array $row): array
    {
        $tenantId = $this->context->id();

        $validator = Validator::make($row, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'nullable', 'email',
                function (string $attr, mixed $value, callable $fail) use ($tenantId): void {
                    $exists = Employee::query()->where('tenant_id', $tenantId)->where('email', $value)->exists();
                    if ($exists) {
                        $fail("An employee with email {$value} already exists.");
                    }
                },
            ],
            'date_of_birth' => ['nullable', 'date'],
            'hire_date' => ['nullable', 'date'],
            'pay_frequency' => ['nullable', 'in:monthly,biweekly,weekly,daily,hourly'],
        ]);

        return $validator->errors()->all();
    }

    /**
     * Map a normalised row to CreateEmployee attributes, resolving lookups.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function toAttributes(array $row): array
    {
        $attributes = [
            'employee_number' => $row['employee_number'],
            'first_name' => $row['first_name'],
            'middle_name' => $row['middle_name'],
            'last_name' => $row['last_name'],
            'preferred_name' => $row['preferred_name'],
            'email' => $row['email'],
            'personal_email' => $row['personal_email'],
            'phone' => $row['phone'],
            'date_of_birth' => $row['date_of_birth'],
            'gender' => $row['gender'],
            'marital_status' => $row['marital_status'],
            'nationality' => $row['nationality'],
            'job_title' => $row['job_title'],
            'hire_date' => $row['hire_date'],
            'status' => EmploymentStatus::Active->value,
            'pay_frequency' => $row['pay_frequency'],
            'employment_type_id' => $this->lookupId(EmploymentType::class, $row['employment_type']),
            'department_id' => $this->lookupId(Department::class, $row['department']),
            'designation_id' => $this->lookupId(Designation::class, $row['designation']),
            'branch_id' => $this->lookupId(Branch::class, $row['branch']),
        ];

        if ($row['salary_amount'] !== null && is_numeric($row['salary_amount'])) {
            $attributes['salary_amount_cents'] = (int) round(((float) $row['salary_amount']) * 100);
        }

        $statutory = array_filter([
            'tax_pin' => $row['tax_pin'],
            'nssf_number' => $row['nssf_number'],
            'shif_number' => $row['shif_number'],
        ], static fn ($v): bool => $v !== null);

        if ($statutory !== []) {
            $attributes['statutory'] = $statutory;
        }

        return array_filter($attributes, static fn ($v): bool => $v !== null);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function lookupId(string $model, ?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        /** @var Model|null $row */
        $row = $model::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        return $row?->getKey();
    }

    /**
     * @param  class-string  $enum
     */
    private function enumValue(string $enum, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $candidate = mb_strtolower($value);

        foreach ($enum::cases() as $case) {
            if ($case->value === $candidate || mb_strtolower($case->name) === $candidate) {
                return $case->value;
            }
        }

        return null;
    }

    private function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return $value; // let validation flag it
        }
    }
}
