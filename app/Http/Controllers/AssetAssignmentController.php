<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Services\Assets\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetAssignmentController extends Controller
{
    public function __construct(private readonly AssetService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $assignments = AssetAssignment::query()
            ->with(['asset:id,asset_tag,name', 'employee:id,first_name,last_name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->latest('id')
            ->get();

        return response()->json(['data' => $assignments->map(fn (AssetAssignment $a): array => $this->transform($a))]);
    }

    public function store(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', Tenancy::id())],
            'assigned_on' => ['nullable', 'date'],
            'due_back_on' => ['nullable', 'date', 'after_or_equal:assigned_on'],
            'condition_out' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = Employee::query()->findOrFail((int) $data['employee_id']);

        $assignment = $this->service->assign($asset, $employee, [
            'assigned_on' => $data['assigned_on'] ?? null,
            'due_back_on' => $data['due_back_on'] ?? null,
            'condition_out' => $data['condition_out'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $request->user());

        return response()->json(['data' => $this->transform($assignment->load(['asset', 'employee']))], 201);
    }

    public function returnAsset(Request $request, AssetAssignment $assetAssignment): JsonResponse
    {
        $this->authorize('update', $assetAssignment->asset);

        $data = $request->validate([
            'returned_on' => ['nullable', 'date'],
            'condition_in' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'in:available,in_repair'],
        ]);

        $updated = $this->service->checkIn($assetAssignment, [
            'returned_on' => $data['returned_on'] ?? null,
            'condition_in' => $data['condition_in'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'available',
        ]);

        return response()->json(['data' => $this->transform($updated->load(['asset', 'employee']))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(AssetAssignment $assignment): array
    {
        return [
            'uuid' => $assignment->uuid,
            'asset' => $assignment->relationLoaded('asset') ? $assignment->asset->asset_tag.' — '.$assignment->asset->name : null,
            'employee' => $assignment->relationLoaded('employee') ? $assignment->employee->fullName() : null,
            'assigned_on' => $assignment->assigned_on->toDateString(),
            'due_back_on' => $assignment->due_back_on?->toDateString(),
            'returned_on' => $assignment->returned_on?->toDateString(),
            'status' => $assignment->status->value,
            'condition_out' => $assignment->condition_out,
            'condition_in' => $assignment->condition_in,
        ];
    }
}
