<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PerformanceCycleStatus;
use App\Enums\PerformanceCycleType;
use App\Models\PerformanceCycle;
use App\Services\Performance\PerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Enum;

class PerformanceCycleController extends Controller
{
    public function __construct(private readonly PerformanceService $service) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', PerformanceCycle::class);

        $cycles = PerformanceCycle::query()
            ->withCount(['goals', 'reviews'])
            ->orderByDesc('starts_on')
            ->get();

        return response()->json(['data' => $cycles->map(fn (PerformanceCycle $c): array => $this->transform($c))]);
    }

    public function show(PerformanceCycle $performanceCycle): JsonResponse
    {
        $this->authorize('view', $performanceCycle);

        $performanceCycle->loadCount(['goals', 'reviews']);
        $performanceCycle->loadCount(['reviews as submitted_reviews_count' => fn ($q) => $q->whereIn('status', ['submitted', 'acknowledged'])]);

        return response()->json(['data' => $this->transform($performanceCycle, detailed: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PerformanceCycle::class);

        $data = $this->validated($request);

        $this->assertWeights(
            (int) ($data['goal_weight'] ?? config('performance.goal_weight', 60)),
            (int) ($data['competency_weight'] ?? config('performance.competency_weight', 40)),
        );

        $cycle = PerformanceCycle::query()->create($data + [
            'status' => PerformanceCycleStatus::Draft,
            'created_by' => $request->user()?->getKey(),
        ]);

        return response()->json(['data' => $this->transform($cycle->fresh())], 201);
    }

    public function update(Request $request, PerformanceCycle $performanceCycle): JsonResponse
    {
        $this->authorize('update', $performanceCycle);

        abort_if($performanceCycle->status->isClosed(), 422, 'A closed cycle cannot be edited.');

        $data = $this->validated($request, $performanceCycle);

        $this->assertWeights(
            (int) ($data['goal_weight'] ?? $performanceCycle->goal_weight),
            (int) ($data['competency_weight'] ?? $performanceCycle->competency_weight),
        );

        $performanceCycle->update($data);

        return response()->json(['data' => $this->transform($performanceCycle->fresh())]);
    }

    public function destroy(PerformanceCycle $performanceCycle): Response
    {
        $this->authorize('delete', $performanceCycle);

        abort_unless($performanceCycle->status === PerformanceCycleStatus::Draft, 422, 'Only a draft cycle can be deleted.');

        $performanceCycle->delete();

        return response()->noContent();
    }

    public function open(PerformanceCycle $performanceCycle): JsonResponse
    {
        $this->authorize('update', $performanceCycle);

        $created = $this->service->openCycle($performanceCycle);

        return response()->json([
            'data' => $this->transform($performanceCycle->fresh()),
            'meta' => ['reviews_created' => $created],
        ]);
    }

    public function startReview(PerformanceCycle $performanceCycle): JsonResponse
    {
        $this->authorize('update', $performanceCycle);

        $this->service->startReviewPhase($performanceCycle);

        return response()->json(['data' => $this->transform($performanceCycle->fresh())]);
    }

    public function close(PerformanceCycle $performanceCycle): JsonResponse
    {
        $this->authorize('update', $performanceCycle);

        $this->service->closeCycle($performanceCycle);

        return response()->json(['data' => $this->transform($performanceCycle->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PerformanceCycle $cycle = null): array
    {
        $required = $cycle === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:150'],
            'type' => [$required, new Enum(PerformanceCycleType::class)],
            'starts_on' => [$required, 'date'],
            'ends_on' => [$required, 'date', 'after_or_equal:starts_on'],
            'self_review_enabled' => ['boolean'],
            'peer_review_enabled' => ['boolean'],
            'goal_weight' => ['sometimes', 'integer', 'between:0,100'],
            'competency_weight' => ['sometimes', 'integer', 'between:0,100'],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function assertWeights(int $goalWeight, int $competencyWeight): void
    {
        abort_if($goalWeight + $competencyWeight !== 100, 422, 'goal_weight and competency_weight must add up to 100.');
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(PerformanceCycle $cycle, bool $detailed = false): array
    {
        $base = [
            'uuid' => $cycle->uuid,
            'name' => $cycle->name,
            'type' => $cycle->type->value,
            'status' => $cycle->status->value,
            'starts_on' => $cycle->starts_on->toDateString(),
            'ends_on' => $cycle->ends_on->toDateString(),
            'goals_count' => $cycle->goals_count ?? null,
            'reviews_count' => $cycle->reviews_count ?? null,
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'self_review_enabled' => $cycle->self_review_enabled,
            'peer_review_enabled' => $cycle->peer_review_enabled,
            'goal_weight' => $cycle->goal_weight,
            'competency_weight' => $cycle->competency_weight,
            'instructions' => $cycle->instructions,
            'submitted_reviews_count' => $cycle->submitted_reviews_count ?? null,
        ];
    }
}
