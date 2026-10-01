<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AssetCategory;
use App\Enums\AssetStatus;
use App\Facades\Tenancy;
use App\Models\Asset;
use App\Services\Assets\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class AssetController extends Controller
{
    public function __construct(private readonly AssetService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $assets = Asset::query()
            ->with(['branch:id,name', 'activeAssignments.employee:id,first_name,last_name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')->toString()))
            ->orderBy('asset_tag')
            ->get();

        return response()->json(['data' => $assets->map(fn (Asset $a): array => $this->transform($a))]);
    }

    public function show(Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        $asset->load(['branch:id,name', 'assignments.employee:id,first_name,last_name']);

        return response()->json(['data' => $this->transform($asset, detailed: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Asset::class);

        $asset = Asset::query()->create($this->validated($request));

        return response()->json(['data' => $this->transform($asset->fresh(), detailed: true)], 201);
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $asset->update($this->validated($request, $asset));

        return response()->json(['data' => $this->transform($asset->fresh(), detailed: true)]);
    }

    public function retire(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $status = $request->string('status')->toString() === 'lost' ? AssetStatus::Lost : AssetStatus::Retired;
        $this->service->retire($asset, $status);

        return response()->json(['data' => $this->transform($asset->fresh(), detailed: true)]);
    }

    public function destroy(Asset $asset): Response
    {
        $this->authorize('delete', $asset);

        DB::transaction(function () use ($asset): void {
            $asset->assignments()->delete();
            $asset->delete();
        });

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Asset $asset = null): array
    {
        $required = $asset === null ? 'required' : 'sometimes';
        $tenantId = Tenancy::id();

        return $request->validate([
            'asset_tag' => [$required, 'string', 'max:60', Rule::unique('assets', 'asset_tag')->where('tenant_id', $tenantId)->ignore($asset?->getKey())],
            'name' => [$required, 'string', 'max:150'],
            'category' => [$required, new Enum(AssetCategory::class)],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'purchased_on' => ['nullable', 'date'],
            'purchase_cost_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'warranty_expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Asset $asset, bool $detailed = false): array
    {
        $holder = $asset->relationLoaded('activeAssignments')
            ? $asset->activeAssignments->first()?->employee?->fullName()
            : null;

        $base = [
            'uuid' => $asset->uuid,
            'asset_tag' => $asset->asset_tag,
            'name' => $asset->name,
            'category' => $asset->category->value,
            'status' => $asset->status->value,
            'branch' => $asset->relationLoaded('branch') ? $asset->branch?->name : null,
            'assigned_to' => $holder,
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'serial_number' => $asset->serial_number,
            'description' => $asset->description,
            'purchased_on' => $asset->purchased_on?->toDateString(),
            'purchase_cost_cents' => $asset->purchase_cost_cents,
            'currency' => $asset->currency,
            'warranty_expires_on' => $asset->warranty_expires_on?->toDateString(),
            'assignments' => $asset->relationLoaded('assignments')
                ? $asset->assignments->map(fn ($a): array => [
                    'uuid' => $a->uuid,
                    'employee' => $a->relationLoaded('employee') ? $a->employee->fullName() : null,
                    'assigned_on' => $a->assigned_on->toDateString(),
                    'returned_on' => $a->returned_on?->toDateString(),
                    'status' => $a->status->value,
                ])->all()
                : [],
        ];
    }
}
