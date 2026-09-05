<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CalculationMethod;
use App\Enums\SalaryComponentType;
use App\Exceptions\PayrollException;
use App\Facades\Tenancy;
use App\Models\SalaryComponent;
use App\Services\Payroll\FormulaEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class SalaryComponentController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', SalaryComponent::class);

        return response()->json([
            'data' => SalaryComponent::query()->orderBy('sort_order')->get()->map(fn (SalaryComponent $c): array => $this->transform($c)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SalaryComponent::class);

        $component = SalaryComponent::query()->create($this->validated($request));

        return response()->json(['data' => $this->transform($component->fresh())], 201);
    }

    public function update(Request $request, SalaryComponent $salaryComponent): JsonResponse
    {
        $this->authorize('update', $salaryComponent);

        $salaryComponent->update($this->validated($request, $salaryComponent));

        return response()->json(['data' => $this->transform($salaryComponent->fresh())]);
    }

    public function destroy(SalaryComponent $salaryComponent): Response
    {
        $this->authorize('delete', $salaryComponent);

        abort_if($salaryComponent->is_statutory, 409, 'Statutory components cannot be deleted.');
        $salaryComponent->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?SalaryComponent $component = null): array
    {
        $required = $component === null ? 'required' : 'sometimes';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'code' => [
                $required, 'string', 'max:40', 'alpha_dash',
                Rule::unique('salary_components', 'code')->where('tenant_id', Tenancy::id())->ignore($component?->id)->whereNull('deleted_at'),
            ],
            'type' => [$required, new Enum(SalaryComponentType::class)],
            'calculation_method' => [$required, new Enum(CalculationMethod::class)],
            'amount_cents' => ['nullable', 'integer', 'min:0'],
            'rate' => ['nullable', 'numeric', 'between:0,10'],
            'base_code' => ['nullable', 'string', 'max:40'],
            'formula' => ['nullable', 'string', 'max:500'],
            'is_taxable' => ['boolean'],
            'is_pensionable' => ['boolean'],
            'is_recurring' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        if (! empty($data['formula'])) {
            try {
                app(FormulaEvaluator::class)->evaluate($data['formula'], [
                    'basic_salary' => 1, 'gross_earnings' => 1, 'working_days' => 1, 'days_worked' => 1,
                    'overtime_hours' => 1, 'overtime_rate' => 1, 'hourly_rate' => 1,
                ]);
            } catch (PayrollException $e) {
                throw ValidationException::withMessages(['formula' => $e->getMessage()]);
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(SalaryComponent $c): array
    {
        return [
            'uuid' => $c->uuid,
            'name' => $c->name,
            'code' => $c->code,
            'type' => $c->type->value,
            'calculation_method' => $c->calculation_method->value,
            'amount_cents' => $c->amount_cents,
            'rate' => $c->rate,
            'base_code' => $c->base_code,
            'formula' => $c->formula,
            'is_taxable' => $c->is_taxable,
            'is_pensionable' => $c->is_pensionable,
            'is_statutory' => $c->is_statutory,
            'is_recurring' => $c->is_recurring,
            'sort_order' => $c->sort_order,
            'is_active' => $c->is_active,
        ];
    }
}
