<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TaxRuleType;
use App\Models\TaxRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Enum;

class TaxRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TaxRule::class);

        return response()->json([
            'data' => TaxRule::query()
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
                ->orderBy('type')->orderByDesc('effective_from')
                ->get()->map(fn (TaxRule $r): array => $this->transform($r)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TaxRule::class);

        $rule = TaxRule::query()->create($this->validated($request));

        return response()->json(['data' => $this->transform($rule->fresh())], 201);
    }

    public function update(Request $request, TaxRule $taxRule): JsonResponse
    {
        $this->authorize('update', $taxRule);

        $taxRule->update($this->validated($request, $taxRule));

        return response()->json(['data' => $this->transform($taxRule->fresh())]);
    }

    public function destroy(TaxRule $taxRule): Response
    {
        $this->authorize('delete', $taxRule);
        $taxRule->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?TaxRule $rule = null): array
    {
        $required = $rule === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'type' => [$required, new Enum(TaxRuleType::class)],
            'country' => ['nullable', 'string', 'size:2'],
            'effective_from' => [$required, 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'config' => [$required, 'array'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(TaxRule $rule): array
    {
        return [
            'uuid' => $rule->uuid,
            'name' => $rule->name,
            'type' => $rule->type->value,
            'country' => $rule->country,
            'effective_from' => $rule->effective_from->toDateString(),
            'effective_to' => $rule->effective_to?->toDateString(),
            'config' => $rule->config,
            'is_active' => $rule->is_active,
        ];
    }
}
