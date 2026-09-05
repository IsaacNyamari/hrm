<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\InterviewMode;
use App\Enums\InterviewRecommendation;
use App\Enums\InterviewStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InterviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $application_id
 * @property int $round
 * @property InterviewMode $mode
 * @property int|null $interviewer_id
 * @property CarbonImmutable $scheduled_at
 * @property int $duration_minutes
 * @property string|null $location
 * @property string|null $meeting_url
 * @property InterviewStatus $status
 * @property InterviewRecommendation|null $recommendation
 * @property int|null $rating
 * @property string|null $feedback
 * @property CarbonImmutable|null $feedback_submitted_at
 * @property int|null $scheduled_by
 * @property-read Application $application
 */
class Interview extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<InterviewFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => InterviewMode::class,
            'status' => InterviewStatus::class,
            'recommendation' => InterviewRecommendation::class,
            'round' => 'integer',
            'duration_minutes' => 'integer',
            'rating' => 'integer',
            'scheduled_at' => 'immutable_datetime',
            'feedback_submitted_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }
}
