<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\PayrollFrequency;
use App\Enums\PayrollPeriodStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PayrollPeriodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $employee_id set on an off-cycle final settlement
 * @property string $uuid
 * @property string $name
 * @property PayrollFrequency $frequency
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property CarbonImmutable $pay_date
 * @property PayrollPeriodStatus $status
 */
class PayrollPeriod extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @use HasFactory<PayrollPeriodFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => PayrollFrequency::class,
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'pay_date' => 'immutable_date',
            'status' => PayrollPeriodStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * The exited employee this final settlement pays (null for regular periods).
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isFinalSettlement(): bool
    {
        return $this->employee_id !== null;
    }

    /**
     * @param  Builder<PayrollPeriod>  $query
     */
    public function scopeRegular(Builder $query): void
    {
        $query->whereNull('employee_id');
    }

    /**
     * @return HasMany<PayrollRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    public function latestRun(): ?PayrollRun
    {
        return $this->runs()->latest('id')->first();
    }
}
