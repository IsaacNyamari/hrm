<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\Department;
use App\Services\Organization\OrgChartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::query()
            ->with(['parent:id,name', 'branch:id,name', 'head:id,first_name,last_name'])
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $departments->map(fn (Department $d): array => $this->transform($d))]);
    }

    public function tree(OrgChartService $chart): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        return response()->json(['data' => $chart->departmentTree()]);
    }

    public function show(Department $department): JsonResponse
    {
        $this->authorize('view', $department);

        return response()->json(['data' => $this->transform(
            $department->load(['parent:id,name', 'branch:id,name', 'head:id,first_name,last_name'])->loadCount('employees'),
        )]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Department::class);

        $department = Department::query()->create($this->validated($request));

        return response()->json(['data' => $this->transform($department)], 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $this->authorize('update', $department);

        $data = $this->validated($request, $department);

        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null
            && in_array((int) $data['parent_id'], $department->subtreeIds(), true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A department cannot be nested under itself or one of its sub-departments.',
            ]);
        }

        $department->update($data);

        return response()->json(['data' => $this->transform($department->fresh())]);
    }

    public function destroy(Department $department): Response
    {
        $this->authorize('delete', $department);

        abort_if($department->children()->exists(), 409, 'Move or delete sub-departments first.');
        abort_if($department->employees()->exists(), 409, 'Reassign employees before deleting this department.');
        $department->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Department $department = null): array
    {
        $required = $department === null ? 'required' : 'sometimes';
        $tenantId = Tenancy::id();

        return $request->validate([
            'name' => [$required, 'string', 'max:150'],
            'code' => [
                'nullable', 'string', 'max:30',
                Rule::unique('departments', 'code')->where('tenant_id', $tenantId)->ignore($department?->id)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'different:id', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'head_employee_id' => ['nullable', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Department $department): array
    {
        return [
            'uuid' => $department->uuid,
            'name' => $department->name,
            'code' => $department->code,
            'parent' => $department->parent?->name,
            'branch' => $department->branch?->name,
            'head' => $department->head?->fullName(),
            'is_active' => $department->is_active,
            'employees_count' => $department->getAttribute('employees_count'),
        ];
    }
}
