<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\Employee;
use App\Models\SalaryComponent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeSalaryController extends Controller
{
    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('viewAny', SalaryComponent::class);

        $employee->load('salaryComponents.component');

        return response()->json(['data' => [
            'base_salary_cents' => $employee->salary_amount_cents,
            'currency' => $employee->salary_currency,
            'pay_frequency' => $employee->pay_frequency,
            'components' => $employee->salaryComponents->map(fn ($esc): array => [
                'code' => $esc->component?->code,
                'name' => $esc->component?->name,
                'amount_cents' => $esc->amount_cents,
                'rate' => $esc->rate,
                'effective_from' => $esc->effective_from->toDateString(),
                'effective_to' => $esc->effective_to?->toDateString(),
            ])->all(),
        ]]);
    }

    /**
     * Replace the employee's salary structure with the supplied lines.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('create', SalaryComponent::class);

        $data = $request->validate([
            'base_salary_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'pay_frequency' => ['nullable', 'in:monthly,biweekly,weekly,daily,hourly'],
            'effective_from' => ['nullable', 'date'],
            'components' => ['sometimes', 'array'],
            'components.*.code' => ['required_with:components', Rule::exists('salary_components', 'code')->where('tenant_id', Tenancy::id())],
            'components.*.amount_cents' => ['nullable', 'integer', 'min:0'],
            'components.*.rate' => ['nullable', 'numeric', 'between:0,10'],
        ]);

        $effectiveFrom = CarbonImmutable::parse($data['effective_from'] ?? CarbonImmutable::now()->startOfMonth()->toDateString());

        DB::transaction(function () use ($employee, $data, $effectiveFrom): void {
            $employee->fill(array_filter([
                'salary_amount_cents' => $data['base_salary_cents'] ?? null,
                'salary_currency' => $data['currency'] ?? null,
                'pay_frequency' => $data['pay_frequency'] ?? null,
            ], fn ($v) => $v !== null))->save();

            if (array_key_exists('components', $data)) {
                $employee->salaryComponents()->delete();

                $byCode = SalaryComponent::query()->get()->keyBy('code');

                foreach ($data['components'] as $line) {
                    $component = $byCode[$line['code']] ?? null;
                    if ($component === null) {
                        continue;
                    }

                    $employee->salaryComponents()->create([
                        'tenant_id' => $employee->tenant_id,
                        'salary_component_id' => $component->id,
                        'amount_cents' => $line['amount_cents'] ?? null,
                        'rate' => $line['rate'] ?? null,
                        'effective_from' => $effectiveFrom->toDateString(),
                    ]);
                }
            }
        });

        return $this->show($employee->fresh());
    }
}
