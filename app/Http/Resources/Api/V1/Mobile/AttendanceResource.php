<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Mobile;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attendance
 */
class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'work_date' => $this->work_date->toDateString(),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee->fullName()),
            'employee_number' => $this->whenLoaded('employee', fn () => $this->employee->employee_number),
            'clock_in_at' => $this->clock_in_at?->toIso8601String(),
            'clock_out_at' => $this->clock_out_at?->toIso8601String(),
            'break_started_at' => $this->break_started_at?->toIso8601String(),
            'on_break' => $this->isOnBreak(),
            'break_minutes' => $this->break_minutes,
            'worked_minutes' => $this->worked_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'source' => $this->source?->value,
            'shift' => $this->whenLoaded('shift', fn () => $this->shift?->name),
            'breaks' => $this->whenLoaded('breaks', fn () => $this->breaks->map(fn (AttendanceBreak $b): array => [
                'started_at' => $b->started_at->toIso8601String(),
                'ended_at' => $b->ended_at?->toIso8601String(),
                'minutes' => $b->minutes,
            ])->values()->all()),
        ];
    }
}
