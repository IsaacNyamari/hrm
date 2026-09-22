<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A signed-in mobile device (one `mobile` Sanctum token).
 *
 * @mixin PersonalAccessToken
 */
class DeviceSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $current = $request->user()?->currentAccessToken();

        return [
            'id' => $this->id,
            'device_name' => $this->name,
            'current' => $current instanceof PersonalAccessToken && $current->getKey() === $this->getKey(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
