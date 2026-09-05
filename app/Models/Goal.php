<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\GoalCategory;
use App\Enums\GoalStatus;
use Carbon\CarbonImmutable;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int|null $performance_cycle_id
 * @property int $employee_id
 * @property string $title
 * @property string|null $description
 * @property GoalCategory $category
 * @property int $weight
 * @property string|null $metric
 * @property string|null $target
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $due_date
 * @property GoalStatus $status
 * @property int $progress
 * @property float|null $self_rating
 * @property float|null $manager_rating
 * @property string|null $manager_comment
 * @property int|null $created_by
 * @property-read Employee $employee
 * @property-read PerformanceCycle|null $cycle
 */
class Goal extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => GoalCategory::class,
            'status' => GoalStatus::class,
            'weight' => 'integer',
            'progress' => 'integer',
            'start_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'self_rating' => 'float',
            'manager_rating' => 'float',
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
     * @return HasMany<GoalCheckIn, $this>
     */
    public function checkIns(): HasMany
    {
        return $this->hasMany(GoalCheckIn::class)->latest('id');
    }
}
