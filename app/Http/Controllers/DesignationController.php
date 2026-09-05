<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\Designation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class DesignationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Designation::class);

        $designations = Designation::query()
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->with('department:id,name')
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $designations->map(fn (Designation $d): array => $this->transform($d))]);
    }

    public function show(Designation $designation): JsonResponse
    {
        $this->authorize('view', $designation);

        return response()->json(['data' => $this->transform($designation->load('department:id,name')->loadCount('employees'))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Designation::class);

        $designation = Designation::query()->create($this->validated($request));

        return response()->json(['data' => $this->transform($designation)], 201);
    }

    public function update(Request $request, Designation $designation): JsonResponse
    {
        $this->authorize('update', $designation);

        $designation->update($this->validated($request, $designation));

        return response()->json(['data' => $this->transform($designation)]);
    }

    public function destroy(Designation $designation): Response
    {
        $this->authorize('delete', $designation);

        abort_if($designation->employees()->exists(), 409, 'Reassign employees before deleting this designation.');
        $designation->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Designation $designation = null): array
    {
        $required = $designation === null ? 'required' : 'sometimes';
        $tenantId = Tenancy::id();

        return $request->validate([
            'name' => [$required, 'string', 'max:150'],
            'code' => [
                'nullable', 'string', 'max:30',
                Rule::unique('designations', 'code')->where('tenant_id', $tenantId)->ignore($designation?->id)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Designation $designation): array
    {
        return [
            'uuid' => $designation->uuid,
            'name' => $designation->name,
            'code' => $designation->code,
            'department' => $designation->department?->name,
            'is_active' => $designation->is_active,
            'employees_count' => $designation->getAttribute('employees_count'),
        ];
    }
}
