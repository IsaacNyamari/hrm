<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Mobile;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveType
 */
class LeaveTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'is_paid' => $this->is_paid,
            'requires_approval' => $this->requires_approval,
            'requires_attachment' => $this->requires_attachment,
            'min_notice_days' => $this->min_notice_days,
            'max_consecutive_days' => $this->max_consecutive_days,
        ];
    }
}
