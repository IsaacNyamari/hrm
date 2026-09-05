<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $year
 * @property float $allocated_days
 * @property float $carried_forward_days
 * @property float $adjustment_days
 * @property float $used_days
 * @property float $pending_days
 * @property float $expired_days
 */
class LeaveBalance extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'allocated_days' => 'float',
            'carried_forward_days' => 'float',
            'adjustment_days' => 'float',
            'used_days' => 'float',
            'pending_days' => 'float',
            'expired_days' => 'float',
        ];
    }

    /**
     * Days the employee can still request this year.
     */
    public function remainingDays(): float
    {
        return round(
            $this->allocated_days + $this->carried_forward_days + $this->adjustment_days
            - $this->used_days - $this->pending_days - $this->expired_days,
            2,
        );
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
