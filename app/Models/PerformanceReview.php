<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\ReviewerType;
use App\Enums\ReviewStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PerformanceReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $performance_cycle_id
 * @property int $employee_id
 * @property int|null $reviewer_id
 * @property ReviewerType $reviewer_type
 * @property ReviewStatus $status
 * @property float|null $overall_rating
 * @property float|null $final_score
 * @property string|null $strengths
 * @property string|null $improvements
 * @property string|null $comments
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $acknowledged_at
 * @property string|null $acknowledgement_note
 * @property-read Employee $employee
 * @property-read PerformanceCycle $cycle
 * @property-read User|null $reviewer
 */
class PerformanceReview extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PerformanceReviewFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewer_type' => ReviewerType::class,
            'status' => ReviewStatus::class,
            'overall_rating' => 'float',
            'final_score' => 'float',
            'submitted_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<PerformanceCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @return HasMany<ReviewRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(ReviewRating::class);
    }
}
