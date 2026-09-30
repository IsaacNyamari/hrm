<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\PayrollRunStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $payroll_period_id
 * @property PayrollRunStatus $status
 * @property int $employee_count
 * @property int $gross_cents
 * @property int $taxable_cents
 * @property int $statutory_cents
 * @property int $deductions_cents
 * @property int $net_cents
 * @property int|null $processed_by
 * @property CarbonImmutable|null $processed_at
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $locked_at
 * @property-read PayrollPeriod $period
 */
class PayrollRun extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayrollRunStatus::class,
            'employee_count' => 'integer',
            'gross_cents' => 'integer',
            'taxable_cents' => 'integer',
            'statutory_cents' => 'integer',
            'deductions_cents' => 'integer',
            'net_cents' => 'integer',
            'processed_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
