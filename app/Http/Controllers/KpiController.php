<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\KpiDirection;
use App\Facades\Tenancy;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\Kpi;
use App\Models\PerformanceCycle;
use App\Services\Performance\PerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class KpiController extends Controller
{
    public function __construct(private readonly PerformanceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Kpi::class);

        $user = $request->user();
        $canViewAll = $user?->can('performance.view') || $user?->can('performance.manage');
        $ownEmployeeId = $user?->employee?->getKey();
        $managedIds = $ownEmployeeId === null ? [] : Employee::query()->where('manager_id', $ownEmployeeId)->pluck('id')->all();

        $kpis = Kpi::query()
            ->with('employee:id,first_name,last_name,employee_number')
            ->when(! $canViewAll, function ($q) use ($ownEmployeeId, $managedIds): void {
                $q->where(fn ($inner) => $inner->where('employee_id', $ownEmployeeId)->orWhereIn('employee_id', $managedIds));
            })
            ->when($request->filled('employee_id') && $canViewAll, fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('cycle_uuid'), fn ($q) => $q->whereRelation('cycle', 'uuid', $request->string('cycle_uuid')->toString()))
            ->latest('id')
            ->get();

        return response()->json(['data' => $kpis->map(fn (Kpi $k): array => $this->transform($k))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Kpi::class);

        $data = $request->validate([
            'employee_id' => ['nullable', 'integer'],
            'cycle_uuid' => ['nullable', Rule::exists('performance_cycles', 'uuid')->where('tenant_id', Tenancy::id())],
            'goal_uuid' => ['nullable', Rule::exists('goals', 'uuid')->where('tenant_id', Tenancy::id())],
            'name' => ['required', 'string', 'max:200'],
            'unit' => ['nullable', 'string', 'max:40'],
            'direction' => ['sometimes', new Enum(KpiDirection::class)],
            'baseline_value' => ['nullable', 'numeric'],
            'target_value' => ['required', 'numeric'],
            'current_value' => ['sometimes', 'numeric'],
            'weight' => ['sometimes', 'integer', 'between:0,100'],
        ]);

        $employee = $this->resolveEmployee($request, $data['employee_id'] ?? null);

        $cycleId = isset($data['cycle_uuid'])
            ? PerformanceCycle::query()->where('uuid', $data['cycle_uuid'])->value('id')
            : null;
        $goalId = isset($data['goal_uuid'])
            ? Goal::query()->where('uuid', $data['goal_uuid'])->value('id')
            : null;

        $kpi = Kpi::query()->create([
            'employee_id' => $employee->getKey(),
            'performance_cycle_id' => $cycleId,
            'goal_id' => $goalId,
            'name' => $data['name'],
            'unit' => $data['unit'] ?? null,
            'direction' => $data['direction'] ?? KpiDirection::HigherIsBetter->value,
            'baseline_value' => $data['baseline_value'] ?? null,
            'target_value' => $data['target_value'],
            'current_value' => $data['current_value'] ?? 0,
            'weight' => (int) ($data['weight'] ?? 0),
            'created_by' => $request->user()?->getKey(),
        ]);

        return response()->json(['data' => $this->transform($kpi->fresh())], 201);
    }

    public function update(Request $request, Kpi $kpi): JsonResponse
    {
        $this->authorize('update', $kpi);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'unit' => ['nullable', 'string', 'max:40'],
            'direction' => ['sometimes', new Enum(KpiDirection::class)],
            'baseline_value' => ['sometimes', 'nullable', 'numeric'],
            'target_value' => ['sometimes', 'numeric'],
            'weight' => ['sometimes', 'integer', 'between:0,100'],
        ]);

        $kpi->fill($data)->save();

        return response()->json(['data' => $this->transform($kpi->fresh())]);
    }

    public function record(Request $request, Kpi $kpi): JsonResponse
    {
        $this->authorize('update', $kpi);

        $value = $request->validate(['value' => ['required', 'numeric']])['value'];

        $this->service->recordKpi($kpi, (float) $value);

        return response()->json(['data' => $this->transform($kpi->fresh())]);
    }

    public function destroy(Kpi $kpi): Response
    {
        $this->authorize('delete', $kpi);

        $kpi->delete();

        return response()->noContent();
    }

    private function resolveEmployee(Request $request, ?int $employeeId): Employee
    {
        if ($employeeId !== null) {
            abort_unless($request->user()?->can('performance.manage'), 403);

            return Employee::query()->findOrFail($employeeId);
        }

        $employee = $request->user()?->employee;
        abort_if($employee === null, 422, 'Your account is not linked to an employee record.');

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Kpi $kpi): array
    {
        return [
            'uuid' => $kpi->uuid,
            'name' => $kpi->name,
            'unit' => $kpi->unit,
            'direction' => $kpi->direction->value,
            'baseline_value' => $kpi->baseline_value,
            'target_value' => $kpi->target_value,
            'current_value' => $kpi->current_value,
            'weight' => $kpi->weight,
            'attainment' => round($kpi->attainment(), 4),
            'last_recorded_at' => $kpi->last_recorded_at?->toIso8601String(),
            'employee' => $kpi->relationLoaded('employee') ? $kpi->employee->fullName() : null,
        ];
    }
}
