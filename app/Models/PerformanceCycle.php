<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\PerformanceCycleStatus;
use App\Enums\PerformanceCycleType;
use Carbon\CarbonImmutable;
use Database\Factories\PerformanceCycleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $name
 * @property PerformanceCycleType $type
 * @property PerformanceCycleStatus $status
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property bool $self_review_enabled
 * @property bool $peer_review_enabled
 * @property int $goal_weight
 * @property int $competency_weight
 * @property array<int, array{code: string, name: string}>|null $rating_scale
 * @property string|null $instructions
 * @property int|null $created_by
 */
class PerformanceCycle extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PerformanceCycleFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PerformanceCycleType::class,
            'status' => PerformanceCycleStatus::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'self_review_enabled' => 'boolean',
            'peer_review_enabled' => 'boolean',
            'goal_weight' => 'integer',
            'competency_weight' => 'integer',
            'rating_scale' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<Goal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * @return HasMany<Kpi, $this>
     */
    public function kpis(): HasMany
    {
        return $this->hasMany(Kpi::class);
    }

    /**
     * @return HasMany<PerformanceReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }
}
