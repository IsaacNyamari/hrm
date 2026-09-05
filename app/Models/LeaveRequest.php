<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\LeaveStatus;
use Carbon\CarbonImmutable;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $employee_id
 * @property int $leave_type_id
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property bool $half_day_start
 * @property bool $half_day_end
 * @property float $days
 * @property string|null $reason
 * @property string|null $attachment_path
 * @property LeaveStatus $status
 * @property int $current_level
 * @property int|null $requested_by
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_note
 * @property-read Employee $employee
 * @property-read LeaveType $leaveType
 */
class LeaveRequest extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'half_day_start' => 'boolean',
            'half_day_end' => 'boolean',
            'days' => 'float',
            'status' => LeaveStatus::class,
            'current_level' => 'integer',
            'submitted_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
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
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * @return HasMany<LeaveRequestApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(LeaveRequestApproval::class)->orderBy('level');
    }

    public function currentApproval(): ?LeaveRequestApproval
    {
        return $this->approvals->firstWhere('level', $this->current_level);
    }
}
