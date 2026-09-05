<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $employee_id
 * @property int|null $shift_id
 * @property CarbonImmutable $work_date
 * @property CarbonImmutable|null $clock_in_at
 * @property CarbonImmutable|null $clock_out_at
 * @property CarbonImmutable|null $break_started_at
 * @property int $break_minutes
 * @property int|null $worked_minutes
 * @property int $overtime_minutes
 * @property AttendanceStatus $status
 * @property AttendanceSource $source
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property-read Employee $employee
 * @property-read Shift|null $shift
 */
class Attendance extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'immutable_date',
            'clock_in_at' => 'immutable_datetime',
            'clock_out_at' => 'immutable_datetime',
            'break_started_at' => 'immutable_datetime',
            'break_minutes' => 'integer',
            'worked_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'status' => AttendanceStatus::class,
            'source' => AttendanceSource::class,
            'clock_in_lat' => 'float',
            'clock_in_lng' => 'float',
            'clock_out_lat' => 'float',
            'clock_out_lng' => 'float',
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
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return HasMany<AttendanceBreak, $this>
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    public function isClockedIn(): bool
    {
        return $this->clock_in_at !== null && $this->clock_out_at === null;
    }

    public function isOnBreak(): bool
    {
        return $this->break_started_at !== null;
    }

    /**
     * @param  Builder<Attendance>  $query
     */
    public function scopeForMonth(Builder $query, int $year, int $month): void
    {
        $query->whereYear('work_date', $year)->whereMonth('work_date', $month);
    }
}
