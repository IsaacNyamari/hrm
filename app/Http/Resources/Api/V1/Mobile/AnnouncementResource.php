<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Mobile;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    /**
     * @param  array<int, true>  $readIds  Announcement ids the user has read, keyed by id.
     */
    public function __construct(Announcement $resource, private readonly array $readIds = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience,
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'read' => isset($this->readIds[$this->getKey()]),
        ];
    }
}
