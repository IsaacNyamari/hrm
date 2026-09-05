<?php

declare(strict_types=1);

namespace App\Services\Performance;

use App\Enums\GoalStatus;
use App\Enums\PerformanceCycleStatus;
use App\Enums\ReviewerType;
use App\Enums\ReviewStatus;
use App\Events\PerformanceReviewAssigned;
use App\Events\PerformanceReviewSubmitted;
use App\Exceptions\PerformanceException;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\GoalCheckIn;
use App\Models\Kpi;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PerformanceService
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Move a draft cycle to active and fan out one manager review (and, when
     * enabled, one self review) for every active employee. Idempotent.
     */
    public function openCycle(PerformanceCycle $cycle): int
    {
        if ($cycle->status->isClosed()) {
            throw PerformanceException::cycleAlreadyClosed();
        }

        return $this->context->run($cycle->tenant, function () use ($cycle): int {
            return DB::transaction(function () use ($cycle): int {
                if ($cycle->status === PerformanceCycleStatus::Draft) {
                    $cycle->forceFill(['status' => PerformanceCycleStatus::Active])->save();
                }

                $created = 0;

                Employee::query()->active()->get(['id', 'tenant_id', 'user_id', 'manager_id'])
                    ->each(function (Employee $employee) use ($cycle, &$created): void {
                        $created += $this->ensureReview($cycle, $employee, ReviewerType::Manager, $employee->manager?->user_id);

                        if ($cycle->self_review_enabled && $employee->user_id !== null) {
                            $created += $this->ensureReview($cycle, $employee, ReviewerType::SelfReview, $employee->user_id);
                        }
                    });

                return $created;
            });
        });
    }

    public function startReviewPhase(PerformanceCycle $cycle): void
    {
        if ($cycle->status->isClosed()) {
            throw PerformanceException::cycleAlreadyClosed();
        }

        if ($cycle->status !== PerformanceCycleStatus::InReview) {
            $cycle->forceFill(['status' => PerformanceCycleStatus::InReview])->save();
        }
    }

    /**
     * Freeze the cycle and snapshot each employee's final weighted score onto
     * their manager review (or self review if there is no manager review).
     */
    public function closeCycle(PerformanceCycle $cycle): void
    {
        if ($cycle->status->isClosed()) {
            throw PerformanceException::cycleAlreadyClosed();
        }

        $this->context->run($cycle->tenant, function () use ($cycle): void {
            DB::transaction(function () use ($cycle): void {
                $cycle->loadMissing('reviews');
                $byEmployee = $cycle->reviews->groupBy('employee_id');

                foreach ($byEmployee as $reviews) {
                    $employeeId = (int) $reviews->first()->employee_id;
                    $employee = Employee::query()->find($employeeId);

                    if ($employee === null) {
                        continue;
                    }

                    $score = $this->scoreForEmployee($cycle, $employee);
                    $target = $reviews->firstWhere('reviewer_type', ReviewerType::Manager)
                        ?? $reviews->firstWhere('reviewer_type', ReviewerType::SelfReview);

                    $target?->forceFill(['final_score' => $score])->save();
                }

                $cycle->forceFill(['status' => PerformanceCycleStatus::Closed])->save();
            });
        });
    }

    public function checkInGoal(Goal $goal, int $progress, ?string $note, User $actor): GoalCheckIn
    {
        $progress = max(0, min(100, $progress));

        return DB::transaction(function () use ($goal, $progress, $note, $actor): GoalCheckIn {
            $checkIn = $goal->checkIns()->create([
                'tenant_id' => $goal->tenant_id,
                'progress' => $progress,
                'note' => $note,
                'logged_by' => $actor->getKey(),
            ]);

            $attributes = ['progress' => $progress];

            if ($progress >= 100 && $goal->status === GoalStatus::Active) {
                $attributes['status'] = GoalStatus::Completed;
            }

            $goal->forceFill($attributes)->save();

            return $checkIn;
        });
    }

    public function recordKpi(Kpi $kpi, float $value): Kpi
    {
        $kpi->forceFill([
            'current_value' => $value,
            'last_recorded_at' => CarbonImmutable::now(),
        ])->save();

        return $kpi;
    }

    /**
     * @param  array{strengths?: string|null, improvements?: string|null, comments?: string|null, ratings?: array<int, array{competency: string, rating: int|float, weight?: int|null, comment?: string|null}>}  $payload
     */
    public function saveReview(PerformanceReview $review, array $payload): PerformanceReview
    {
        $this->guardEditable($review);

        return DB::transaction(function () use ($review, $payload): PerformanceReview {
            $this->applyNarrative($review, $payload);
            $this->syncRatings($review, $payload['ratings'] ?? null);

            if ($review->status === ReviewStatus::Pending) {
                $review->status = ReviewStatus::InProgress;
            }

            $review->save();

            return $review->load('ratings');
        });
    }

    /**
     * @param  array{strengths?: string|null, improvements?: string|null, comments?: string|null, ratings?: array<int, array{competency: string, rating: int|float, weight?: int|null, comment?: string|null}>}  $payload
     */
    public function submitReview(PerformanceReview $review, array $payload): PerformanceReview
    {
        $this->guardEditable($review);

        if (! $review->cycle->status->acceptsReviews()) {
            throw PerformanceException::cycleNotInReview();
        }

        return DB::transaction(function () use ($review, $payload): PerformanceReview {
            $this->applyNarrative($review, $payload);
            $this->syncRatings($review, $payload['ratings'] ?? null);

            $review->loadMissing('ratings');

            if ($review->ratings->isEmpty()) {
                throw PerformanceException::noRatings();
            }

            $review->forceFill([
                'status' => ReviewStatus::Submitted,
                'overall_rating' => $this->weightedMean(
                    $review->ratings->map(fn ($r): array => [(float) $r->rating, (int) $r->weight])->all(),
                ),
                'submitted_at' => CarbonImmutable::now(),
            ])->save();

            PerformanceReviewSubmitted::dispatch($review);

            return $review->load('ratings');
        });
    }

    public function acknowledgeReview(PerformanceReview $review, ?string $note): PerformanceReview
    {
        if (! $review->status->isSubmitted()) {
            throw PerformanceException::reviewNotSubmitted();
        }

        if ($review->status !== ReviewStatus::Acknowledged) {
            $review->forceFill([
                'status' => ReviewStatus::Acknowledged,
                'acknowledged_at' => CarbonImmutable::now(),
                'acknowledgement_note' => $note,
            ])->save();
        }

        return $review;
    }

    /**
     * Weighted blend of objective attainment (goals + KPIs) and competency
     * ratings, using the cycle's own goal/competency split. Returns null when
     * there is nothing rated yet.
     */
    public function scoreForEmployee(PerformanceCycle $cycle, Employee $employee): ?float
    {
        $goalRating = $this->weightedMeanOrNull(
            $cycle->goals()
                ->where('employee_id', $employee->getKey())
                ->whereNotNull('manager_rating')
                ->get()
                ->map(fn (Goal $g): array => [(float) $g->manager_rating, (int) $g->weight])
                ->all(),
        );

        $max = (int) config('performance.max_rating', 5);
        $kpiRating = $this->weightedMeanOrNull(
            $cycle->kpis()
                ->where('employee_id', $employee->getKey())
                ->get()
                ->map(fn (Kpi $k): array => [round($k->attainment() * $max, 2), (int) $k->weight])
                ->all(),
        );

        $objectives = $this->weightedMeanOrNull(array_values(array_filter([
            $goalRating === null ? null : [$goalRating, 1],
            $kpiRating === null ? null : [$kpiRating, 1],
        ])));

        $competency = $this->weightedMeanOrNull(
            $cycle->reviews()
                ->where('employee_id', $employee->getKey())
                ->where('reviewer_type', ReviewerType::Manager->value)
                ->whereNotNull('overall_rating')
                ->get()
                ->map(fn (PerformanceReview $r): array => [(float) $r->overall_rating, 1])
                ->all(),
        );

        $blend = array_values(array_filter([
            $objectives === null ? null : [$objectives, $cycle->goal_weight],
            $competency === null ? null : [$competency, $cycle->competency_weight],
        ]));

        return $blend === [] ? null : $this->weightedMean($blend);
    }

    /**
     * The competency rows a fresh manager review is seeded with.
     *
     * @return array<int, array{code: string, name: string}>
     */
    public function competencyFramework(): array
    {
        $tenant = $this->context->get();
        $configured = $tenant?->settings['performance']['competencies'] ?? null;
        $source = is_array($configured) && $configured !== [] ? $configured : config('performance.competencies', []);

        $out = [];

        /** @var array<int, mixed> $source */
        foreach ($source as $row) {
            if (! is_array($row) || ! isset($row['code'], $row['name'])) {
                continue;
            }

            $out[] = ['code' => (string) $row['code'], 'name' => (string) $row['name']];
        }

        return $out;
    }

    private function ensureReview(PerformanceCycle $cycle, Employee $employee, ReviewerType $type, ?int $reviewerId): int
    {
        $review = PerformanceReview::query()->firstOrNew([
            'performance_cycle_id' => $cycle->getKey(),
            'employee_id' => $employee->getKey(),
            'reviewer_type' => $type->value,
        ]);

        if ($review->exists) {
            if ($review->reviewer_id === null && $reviewerId !== null) {
                $review->forceFill(['reviewer_id' => $reviewerId])->save();
            }

            return 0;
        }

        $review->forceFill([
            'tenant_id' => $cycle->tenant_id,
            'reviewer_id' => $reviewerId,
            'status' => ReviewStatus::Pending,
        ])->save();

        PerformanceReviewAssigned::dispatch($review);

        return 1;
    }

    private function guardEditable(PerformanceReview $review): void
    {
        if (! $review->status->isEditable()) {
            throw PerformanceException::reviewAlreadySubmitted();
        }
    }

    /**
     * @param  array{strengths?: string|null, improvements?: string|null, comments?: string|null}  $payload
     */
    private function applyNarrative(PerformanceReview $review, array $payload): void
    {
        foreach (['strengths', 'improvements', 'comments'] as $field) {
            if (array_key_exists($field, $payload)) {
                $review->{$field} = $payload[$field];
            }
        }
    }

    /**
     * @param  array<int, array{competency: string, rating: int|float, weight?: int|null, comment?: string|null}>|null  $ratings
     */
    private function syncRatings(PerformanceReview $review, ?array $ratings): void
    {
        if ($ratings === null) {
            return;
        }

        $min = (int) config('performance.min_rating', 1);
        $max = (int) config('performance.max_rating', 5);

        foreach ($ratings as $row) {
            $value = (float) $row['rating'];

            if ($value < $min || $value > $max) {
                throw PerformanceException::ratingOutOfRange($min, $max);
            }

            $review->ratings()->updateOrCreate(
                ['competency' => (string) $row['competency']],
                [
                    'tenant_id' => $review->tenant_id,
                    'rating' => $value,
                    'weight' => (int) ($row['weight'] ?? 0),
                    'comment' => $row['comment'] ?? null,
                ],
            );
        }
    }

    /**
     * @param  array<int, array{0: float, 1: int}>  $pairs
     */
    private function weightedMean(array $pairs): float
    {
        $totalWeight = array_sum(array_map(static fn (array $p): int => max(0, $p[1]), $pairs));

        if ($totalWeight <= 0) {
            $values = array_map(static fn (array $p): float => $p[0], $pairs);

            return $values === [] ? 0.0 : round(array_sum($values) / count($values), 2);
        }

        $weighted = array_sum(array_map(static fn (array $p): float => $p[0] * max(0, $p[1]), $pairs));

        return round($weighted / $totalWeight, 2);
    }

    /**
     * @param  array<int, array{0: float, 1: int}>  $pairs
     */
    private function weightedMeanOrNull(array $pairs): ?float
    {
        return $pairs === [] ? null : $this->weightedMean($pairs);
    }
}
