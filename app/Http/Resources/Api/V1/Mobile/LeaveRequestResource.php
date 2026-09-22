<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Mobile;

use App\Enums\LeaveStatus;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveRequest
 */
class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee->fullName()),
            'employee_number' => $this->whenLoaded('employee', fn () => $this->employee->employee_number),
            'leave_type' => $this->whenLoaded('leaveType', fn () => $this->leaveType->name),
            'leave_type_uuid' => $this->whenLoaded('leaveType', fn () => $this->leaveType->uuid),
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'half_day_start' => $this->half_day_start,
            'half_day_end' => $this->half_day_end,
            'days' => $this->days,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'has_attachment' => $this->attachment_path !== null,
            'decision_note' => $this->decision_note,
            'current_level' => $this->current_level,
            'cancellable' => $this->status === LeaveStatus::Pending
                || ($this->status === LeaveStatus::Approved && $this->start_date->isFuture()),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'approvals' => $this->whenLoaded('approvals', fn () => $this->approvals->map(fn (LeaveRequestApproval $a): array => [
                'level' => $a->level,
                'approver_type' => $a->approver_type,
                'status' => $a->status->value,
                'comment' => $a->comment,
                'decided_at' => $a->decided_at?->toIso8601String(),
            ])->values()->all()),
        ];
    }
}
