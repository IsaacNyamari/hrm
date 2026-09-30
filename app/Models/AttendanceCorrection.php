<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual attendance change waiting for a second person to approve it. The
 * change is only applied when someone other than the requester approves.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $employee_id
 * @property int|null $attendance_id
 * @property string $action add|edit|delete
 * @property CarbonImmutable $work_date
 * @property array<string, mixed>|null $proposed
 * @property string $reason
 * @property string $status pending|approved|rejected
 * @property int|null $requested_by
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_note
 * @property-read Employee $employee
 */
class AttendanceCorrection extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'immutable_date',
            'proposed' => 'array',
            'decided_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
