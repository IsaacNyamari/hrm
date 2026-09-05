<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Database\Factories\ReviewRatingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $performance_review_id
 * @property string $competency
 * @property int $weight
 * @property float $rating
 * @property string|null $comment
 */
class ReviewRating extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ReviewRatingFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'rating' => 'float',
        ];
    }

    /**
     * @return BelongsTo<PerformanceReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'performance_review_id');
    }
}
