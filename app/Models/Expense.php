<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\ExpenseStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $reference
 * @property int $employee_id
 * @property string $title
 * @property string|null $description
 * @property string $currency
 * @property int $total_cents
 * @property ExpenseStatus $status
 * @property int $current_level
 * @property CarbonImmutable|null $incurred_on
 * @property int|null $submitted_by
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_note
 * @property CarbonImmutable|null $reimbursed_at
 * @property string|null $payment_reference
 * @property-read Employee $employee
 */
class Expense extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExpenseStatus::class,
            'total_cents' => 'integer',
            'current_level' => 'integer',
            'incurred_on' => 'immutable_date',
            'submitted_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'reimbursed_at' => 'immutable_datetime',
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
     * @return HasMany<ExpenseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    /**
     * @return HasMany<ExpenseApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(ExpenseApproval::class)->orderBy('level');
    }

    public function currentApproval(): ?ExpenseApproval
    {
        return $this->approvals->firstWhere('level', $this->current_level);
    }
}
