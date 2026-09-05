<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\KpiDirection;
use Carbon\CarbonImmutable;
use Database\Factories\KpiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int|null $performance_cycle_id
 * @property int $employee_id
 * @property int|null $goal_id
 * @property string $name
 * @property string|null $unit
 * @property KpiDirection $direction
 * @property float|null $baseline_value
 * @property float $target_value
 * @property float $current_value
 * @property int $weight
 * @property CarbonImmutable|null $last_recorded_at
 * @property int|null $created_by
 * @property-read Employee $employee
 * @property-read PerformanceCycle|null $cycle
 */
class Kpi extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<KpiFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => KpiDirection::class,
            'baseline_value' => 'float',
            'target_value' => 'float',
            'current_value' => 'float',
            'weight' => 'integer',
            'last_recorded_at' => 'immutable_datetime',
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
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * Fraction of target achieved, clamped to [0, 1]. For a "lower is better"
     * KPI the ratio is inverted around the baseline so that hitting or beating
     * the target scores 1.0.
     */
    public function attainment(): float
    {
        $target = $this->target_value;
        $current = $this->current_value;

        if ($this->direction === KpiDirection::LowerIsBetter) {
            $baseline = $this->baseline_value ?? max($target, $current, 1.0);

            if ($baseline <= $target) {
                return $current <= $target ? 1.0 : 0.0;
            }

            $ratio = ($baseline - $current) / ($baseline - $target);

            return max(0.0, min(1.0, $ratio));
        }

        if ($target <= 0.0) {
            return $current > 0.0 ? 1.0 : 0.0;
        }

        return max(0.0, min(1.0, $current / $target));
    }
}
