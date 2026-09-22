<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Mobile;

use App\Models\Vacancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An open vacancy on the public job board. Loaded through PublicJobs, which
 * lifts tenant scoping for the organisation and department. The description
 * and requirements are only sent for a single job.
 *
 * @mixin Vacancy
 */
class PublicJobResource extends JsonResource
{
    public function __construct(Vacancy $resource, private readonly bool $detailed = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->title,
            'organization' => [
                'name' => $this->tenant->branding?->brand_name ?: $this->tenant->name,
                'slug' => $this->tenant->slug,
                'logo_url' => $this->tenant->branding?->logoUrl(),
            ],
            'department' => $this->department?->name,
            'location' => $this->location,
            'is_remote' => $this->is_remote,
            'min_salary_cents' => $this->min_salary_cents,
            'max_salary_cents' => $this->max_salary_cents,
            'salary_currency' => $this->salary_currency,
            'openings' => $this->remainingHeadcount(),
            'published_at' => $this->published_at?->toIso8601String(),
            'closes_on' => $this->closes_on?->toDateString(),
            'web_url' => route('jobs.show', ['organization' => $this->tenant->slug, 'vacancy' => $this->slug]),
        ];

        if (! $this->detailed) {
            return $base;
        }

        return $base + [
            'description' => $this->description,
            'requirements' => $this->requirements,
        ];
    }
}
