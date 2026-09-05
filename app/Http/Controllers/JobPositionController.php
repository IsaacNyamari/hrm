<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\JobPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class JobPositionController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', JobPosition::class);

        return response()->json([
            'data' => JobPosition::query()->orderBy('title')->get()->map(fn (JobPosition $p): array => $this->transform($p)),
        ]);
    }

    public function show(JobPosition $jobPosition): JsonResponse
    {
        $this->authorize('view', $jobPosition);

        return response()->json(['data' => $this->transform($jobPosition, withDetail: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', JobPosition::class);

        $position = JobPosition::query()->create($this->validated($request));

        return response()->json(['data' => $this->transform($position->fresh(), withDetail: true)], 201);
    }

    public function update(Request $request, JobPosition $jobPosition): JsonResponse
    {
        $this->authorize('update', $jobPosition);

        $jobPosition->update($this->validated($request, $jobPosition));

        return response()->json(['data' => $this->transform($jobPosition->fresh(), withDetail: true)]);
    }

    public function destroy(JobPosition $jobPosition): Response
    {
        $this->authorize('delete', $jobPosition);

        $jobPosition->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?JobPosition $position = null): array
    {
        $required = $position === null ? 'required' : 'sometimes';
        $tenantId = Tenancy::id();

        return $request->validate([
            'title' => [$required, 'string', 'max:150'],
            'code' => [$required, 'string', 'max:40', Rule::unique('job_positions', 'code')->where('tenant_id', $tenantId)->ignore($position?->getKey())],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'designation_id' => ['nullable', 'integer', Rule::exists('designations', 'id')->where('tenant_id', $tenantId)],
            'employment_type_id' => ['nullable', 'integer', 'exists:employment_types,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'requirements' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(JobPosition $position, bool $withDetail = false): array
    {
        $base = [
            'uuid' => $position->uuid,
            'title' => $position->title,
            'code' => $position->code,
            'is_active' => $position->is_active,
        ];

        return $withDetail
            ? $base + ['description' => $position->description, 'requirements' => $position->requirements]
            : $base;
    }
}
