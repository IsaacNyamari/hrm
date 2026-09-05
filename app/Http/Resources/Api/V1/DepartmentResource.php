<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department
 */
class DepartmentResource extends JsonResource
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
            'parent_uuid' => $this->whenLoaded('parent', fn () => $this->parent?->uuid),
            'employee_count' => $this->whenCounted('employees'),
        ];
    }
}
