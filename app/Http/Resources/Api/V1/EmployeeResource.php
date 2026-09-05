<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'employee_number' => $this->employee_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'email' => $this->email,
            'job_title' => $this->job_title,
            'status' => $this->status->value,
            'hire_date' => $this->hire_date?->toDateString(),
            'department' => $this->whenLoaded('department', fn () => $this->department?->name),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'manager_uuid' => $this->whenLoaded('manager', fn () => $this->manager?->uuid),
        ];
    }
}
