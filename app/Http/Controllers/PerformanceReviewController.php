<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Services\Performance\PerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceReviewController extends Controller
{
    public function __construct(private readonly PerformanceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PerformanceReview::class);

        $user = $request->user();
        $canViewAll = $user?->can('performance.view') || $user?->can('performance.manage');
        $ownEmployeeId = $user?->employee?->getKey();
        $managedIds = $ownEmployeeId === null ? [] : Employee::query()->where('manager_id', $ownEmployeeId)->pluck('id')->all();

        $reviews = PerformanceReview::query()
            ->with(['employee:id,first_name,last_name,employee_number', 'cycle:id,name,status', 'reviewer:id,name'])
            ->when(! $canViewAll, function ($q) use ($user, $ownEmployeeId, $managedIds): void {
                $q->where(function ($inner) use ($user, $ownEmployeeId, $managedIds): void {
                    $inner->where('reviewer_id', $user?->getKey())
                        ->orWhere('employee_id', $ownEmployeeId)
                        ->orWhereIn('employee_id', $managedIds);
                });
            })
            ->when($request->filled('cycle_uuid'), fn ($q) => $q->whereRelation('cycle', 'uuid', $request->string('cycle_uuid')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->boolean('assigned_to_me'), fn ($q) => $q->where('reviewer_id', $user?->getKey()))
            ->latest('id')
            ->get();

        return response()->json(['data' => $reviews->map(fn (PerformanceReview $r): array => $this->transform($r))]);
    }

    public function show(PerformanceReview $performanceReview): JsonResponse
    {
        $this->authorize('view', $performanceReview);

        $performanceReview->load(['employee', 'cycle', 'reviewer:id,name', 'ratings']);

        return response()->json(['data' => $this->transform($performanceReview, detailed: true)]);
    }

    public function update(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $this->authorize('submit', $performanceReview);

        $review = $this->service->saveReview($performanceReview, $this->validatePayload($request));

        return response()->json(['data' => $this->transform($review->load(['ratings', 'cycle', 'employee']), detailed: true)]);
    }

    public function submit(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $this->authorize('submit', $performanceReview);

        $review = $this->service->submitReview($performanceReview, $this->validatePayload($request));

        return response()->json(['data' => $this->transform($review->load(['ratings', 'cycle', 'employee']), detailed: true)]);
    }

    public function acknowledge(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $this->authorize('acknowledge', $performanceReview);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;

        $review = $this->service->acknowledgeReview($performanceReview, $note);

        return response()->json(['data' => $this->transform($review->load(['ratings', 'cycle', 'employee']), detailed: true)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'strengths' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'improvements' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'comments' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'ratings' => ['sometimes', 'array'],
            'ratings.*.competency' => ['required_with:ratings', 'string', 'max:120'],
            'ratings.*.rating' => ['required_with:ratings', 'numeric', 'between:0,5'],
            'ratings.*.weight' => ['nullable', 'integer', 'between:0,100'],
            'ratings.*.comment' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(PerformanceReview $review, bool $detailed = false): array
    {
        $base = [
            'uuid' => $review->uuid,
            'reviewer_type' => $review->reviewer_type->value,
            'status' => $review->status->value,
            'overall_rating' => $review->overall_rating,
            'final_score' => $review->final_score,
            'employee' => $review->relationLoaded('employee') ? $review->employee->fullName() : null,
            'cycle' => $review->relationLoaded('cycle') ? $review->cycle->name : null,
            'reviewer' => $review->relationLoaded('reviewer') ? $review->reviewer?->name : null,
            'submitted_at' => $review->submitted_at?->toIso8601String(),
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'strengths' => $review->strengths,
            'improvements' => $review->improvements,
            'comments' => $review->comments,
            'acknowledged_at' => $review->acknowledged_at?->toIso8601String(),
            'acknowledgement_note' => $review->acknowledgement_note,
            'competency_framework' => $this->service->competencyFramework(),
            'ratings' => $review->relationLoaded('ratings')
                ? $review->ratings->map(fn ($r): array => [
                    'competency' => $r->competency,
                    'rating' => $r->rating,
                    'weight' => $r->weight,
                    'comment' => $r->comment,
                ])->all()
                : [],
        ];
    }
}
