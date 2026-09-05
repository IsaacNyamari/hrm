<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use Carbon\CarbonImmutable;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $employee_id
 * @property LoanType $type
 * @property string|null $reference
 * @property int $principal_cents
 * @property float $interest_rate
 * @property int $installments
 * @property int $installment_cents
 * @property int $outstanding_cents
 * @property CarbonImmutable $start_date
 * @property LoanStatus $status
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property-read Employee $employee
 */
class Loan extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LoanType::class,
            'principal_cents' => 'integer',
            'interest_rate' => 'float',
            'installments' => 'integer',
            'installment_cents' => 'integer',
            'outstanding_cents' => 'integer',
            'start_date' => 'immutable_date',
            'status' => LoanStatus::class,
            'approved_at' => 'immutable_datetime',
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
     * @return HasMany<LoanRepayment, $this>
     */
    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    /**
     * Amount to deduct this run — the installment, capped at what is left.
     */
    public function dueThisRun(): int
    {
        return min($this->installment_cents, $this->outstanding_cents);
    }

    public function isRepaying(): bool
    {
        return $this->status === LoanStatus::Active && $this->outstanding_cents > 0;
    }
}
