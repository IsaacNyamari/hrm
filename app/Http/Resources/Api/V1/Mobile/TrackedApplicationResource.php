<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Mobile;

use App\Models\Application;
use App\Support\Recruitment\ApplicationProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a candidate sees when tracking an application without an account — the
 * same plain-language status and five-step timeline as the web tracking page
 * ({@see ApplicationProgress}). Internal notes, ratings and feedback never
 * leave the organisation.
 *
 * @mixin Application
 */
class TrackedApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $progress = ApplicationProgress::for($this->resource);
        $interview = $progress->nextInterview;
        $offer = $progress->pendingOffer;

        return [
            'tracking_code' => $this->tracking_code,
            'applied_at' => $this->applied_at->toIso8601String(),
            'job' => [
                'title' => $this->vacancy->title,
                'slug' => $this->vacancy->slug,
                'location' => $this->vacancy->location,
                'is_remote' => $this->vacancy->is_remote,
            ],
            'organization' => [
                'name' => $this->tenant->name,
                'slug' => $this->tenant->slug,
            ],
            'state' => $progress->state,
            'headline' => $progress->headline,
            'message' => $progress->message,
            'steps' => collect(ApplicationProgress::STEPS)->map(fn (string $step): array => [
                'key' => $step,
                'label' => match ($step) {
                    'applied' => 'Applied',
                    'review' => 'Under review',
                    'interview' => 'Interview',
                    'offer' => 'Offer',
                    'hired' => 'Hired',
                },
                'state' => $progress->steps[$step],
            ])->all(),
            'next_interview' => $interview === null ? null : [
                'scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'duration_minutes' => $interview->duration_minutes,
                'mode' => $interview->mode->value,
                'location' => $interview->location,
                'meeting_url' => $interview->meeting_url,
            ],
            'pending_offer' => $offer === null ? null : [
                'job_title' => $offer->job_title,
                'salary_cents' => $offer->salary_cents,
                'salary_currency' => $offer->salary_currency,
                'start_date' => $offer->start_date?->toDateString(),
                'expires_on' => $offer->expires_on?->toDateString(),
            ],
            'channel' => 'application.'.$this->tracking_code,
        ];
    }
}
