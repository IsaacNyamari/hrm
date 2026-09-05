<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);

        $branches = Branch::query()
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $branches->map(fn (Branch $b): array => $this->transform($b))]);
    }

    public function show(Branch $branch): JsonResponse
    {
        $this->authorize('view', $branch);

        return response()->json(['data' => $this->transform($branch->loadCount('employees'))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Branch::class);

        $branch = Branch::query()->create($this->validated($request));

        return response()->json(['data' => $this->transform($branch)], 201);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);

        $branch->update($this->validated($request, $branch));

        return response()->json(['data' => $this->transform($branch)]);
    }

    public function destroy(Branch $branch): Response
    {
        $this->authorize('delete', $branch);

        abort_if($branch->employees()->exists(), 409, 'Reassign employees before deleting this branch.');
        $branch->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Branch $branch = null): array
    {
        $required = $branch === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:150'],
            'code' => [
                'nullable', 'string', 'max:30',
                Rule::unique('branches', 'code')->where('tenant_id', Tenancy::id())->ignore($branch?->id)->whereNull('deleted_at'),
            ],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'is_headquarters' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Branch $branch): array
    {
        return [
            'uuid' => $branch->uuid,
            'name' => $branch->name,
            'code' => $branch->code,
            'city' => $branch->city,
            'country' => $branch->country,
            'is_headquarters' => $branch->is_headquarters,
            'is_active' => $branch->is_active,
            'employees_count' => $branch->getAttribute('employees_count'),
        ];
    }
}
