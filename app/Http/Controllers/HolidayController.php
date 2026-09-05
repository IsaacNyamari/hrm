<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\HolidayType;
use App\Facades\Tenancy;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class HolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Holiday::class);

        $holidays = Holiday::query()
            ->when($request->filled('year'), fn ($q) => $q->whereYear('date', $request->integer('year')))
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $holidays->map(fn (Holiday $h): array => $this->transform($h))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Holiday::class);

        $holiday = Holiday::query()->create($this->validated($request));

        return response()->json(['data' => $this->transform($holiday->fresh())], 201);
    }

    public function update(Request $request, Holiday $holiday): JsonResponse
    {
        $this->authorize('update', $holiday);

        $holiday->update($this->validated($request, $holiday));

        return response()->json(['data' => $this->transform($holiday->fresh())]);
    }

    public function destroy(Holiday $holiday): Response
    {
        $this->authorize('delete', $holiday);
        $holiday->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Holiday $holiday = null): array
    {
        $required = $holiday === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:150'],
            'date' => [$required, 'date'],
            'type' => ['nullable', new Enum(HolidayType::class)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', Tenancy::id())],
            'is_recurring' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Holiday $holiday): array
    {
        return [
            'uuid' => $holiday->uuid,
            'name' => $holiday->name,
            'date' => $holiday->date->toDateString(),
            'type' => $holiday->type->value,
            'branch_id' => $holiday->branch_id,
            'is_recurring' => $holiday->is_recurring,
            'is_active' => $holiday->is_active,
        ];
    }
}
