<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Mobile;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An employee as the staff directory shows them: who they are, where they sit
 * and how to reach them. No pay, identity or personal details.
 *
 * @mixin Employee
 */
class DirectoryEmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'employee_number' => $this->employee_number,
            'name' => $this->displayName(),
            'job_title' => $this->job_title,
            'department' => $this->whenLoaded('department', fn () => $this->department?->name),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'manager' => $this->whenLoaded('manager', fn () => $this->manager?->fullName()),
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
        ];
    }
}
