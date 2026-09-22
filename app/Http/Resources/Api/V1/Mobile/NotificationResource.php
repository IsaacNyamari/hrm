<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Mobile;

use App\Support\NotificationSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * An in-app notification. `title` / `detail` match the live toast; `data` is
 * the stored payload the app uses to deep-link (e.g. `leave_request_uuid`).
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];
        $summary = NotificationSummary::fromData($data, Str::snake(class_basename($this->type)));

        return [
            'id' => $this->id,
            'type' => (string) ($data['type'] ?? Str::snake(class_basename($this->type))),
            'title' => $summary->title,
            'detail' => $summary->detail,
            'data' => (object) $data,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
