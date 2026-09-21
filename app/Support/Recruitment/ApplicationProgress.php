<?php

declare(strict_types=1);

namespace App\Support\Recruitment;

use App\Enums\ApplicationStatus;
use App\Enums\InterviewStatus;
use App\Enums\OfferStatus;
use App\Models\Application;
use App\Models\Interview;
use App\Models\Offer;
use Carbon\CarbonImmutable;

/**
 * What a candidate sees on the public tracking page: a plain-language status
 * and a five-step timeline. Internal notes, ratings and feedback never leave
 * the organization.
 */
final readonly class ApplicationProgress
{
    public const STEPS = ['applied', 'review', 'interview', 'offer', 'hired'];

    /**
     * @param  array<string, 'done'|'current'|'upcoming'|'failed'>  $steps
     */
    public function __construct(
        public string $state,
        public string $headline,
        public string $message,
        public array $steps,
        public ?Interview $nextInterview,
        public ?Offer $pendingOffer,
    ) {}

    public static function for(Application $application): self
    {
        $application->loadMissing([
            'interviews' => fn ($q) => $q->withoutGlobalScopes(),
            'offers' => fn ($q) => $q->withoutGlobalScopes(),
        ]);

        $interviews = $application->interviews;
        $offer = $application->offers->first();
        $nextInterview = $interviews
            ->filter(fn (Interview $i) => $i->status === InterviewStatus::Scheduled && $i->scheduled_at->greaterThan(CarbonImmutable::now()))
            ->sortBy('scheduled_at')
            ->first();
        $pendingOffer = $offer !== null && $offer->status === OfferStatus::Sent ? $offer : null;

        // The furthest step shown is whichever is further along: the stage HR
        // moved the application to, or what has actually happened.
        $vacancy = $application->vacancy;
        $byStage = match ($application->stage) {
            $vacancy->firstStage() => 'applied',
            'interview' => 'interview',
            'offer' => 'offer',
            $vacancy->finalStage() => 'hired',
            default => 'review',
        };
        $byEvidence = match (true) {
            $application->status === ApplicationStatus::Hired => 'hired',
            $offer !== null && $offer->status !== OfferStatus::Draft => 'offer',
            $interviews->isNotEmpty() => 'interview',
            default => 'applied',
        };
        $reached = self::STEPS[max(array_search($byStage, self::STEPS, true), array_search($byEvidence, self::STEPS, true))];

        [$state, $headline, $message] = match (true) {
            $application->status === ApplicationStatus::Hired && $offer?->converted_employee_id !== null => [
                'hired', 'You\'re hired!', 'Welcome aboard. Check your email for the link to set up your staff account.',
            ],
            $application->status === ApplicationStatus::Hired => [
                'accepted', 'Offer accepted', 'Thank you for accepting. HR is preparing your employee record and will email your account details.',
            ],
            $application->status === ApplicationStatus::Rejected => [
                'unsuccessful', 'Not successful this time', 'Thank you for your interest. The organization has decided not to move forward with your application. You are welcome to apply for other roles.',
            ],
            $application->status === ApplicationStatus::Withdrawn => [
                'withdrawn', 'Application withdrawn', 'This application was withdrawn.',
            ],
            $offer?->status === OfferStatus::Declined => [
                'declined', 'Offer declined', 'You declined the offer for this role.',
            ],
            $pendingOffer !== null => [
                'offer', 'You have a job offer', 'Review the offer below and accept or decline it.',
            ],
            $nextInterview !== null => [
                'interview', 'Interview scheduled', 'You have been invited to an interview. The details are below.',
            ],
            $reached === 'hired' => [
                'accepted', 'You\'re hired!', 'Congratulations! HR is preparing your employee record and will email your account details.',
            ],
            $reached === 'offer' => [
                'offer', 'Offer stage', 'Great news — you have reached the offer stage. Your offer is being prepared and will appear here.',
            ],
            $reached === 'interview' && $interviews->isEmpty() => [
                'interview', 'Shortlisted for interview', 'You have been shortlisted for an interview. The date and details will appear here.',
            ],
            $reached === 'interview' => [
                'interview', 'Interview stage', 'Your interview has taken place and the team is reviewing the outcome.',
            ],
            $reached === 'review' => [
                'review', 'Under review', 'Your application is being reviewed by the hiring team.',
            ],
            default => [
                'received', 'Application received', 'We have received your application. The hiring team will review it soon.',
            ],
        };

        $reachedIndex = array_search($reached, self::STEPS, true);
        $closedBadly = in_array($state, ['unsuccessful', 'withdrawn', 'declined'], true);
        $steps = [];

        foreach (self::STEPS as $index => $step) {
            $steps[$step] = match (true) {
                $index < $reachedIndex => 'done',
                $index === $reachedIndex && $step === 'hired' => 'done',
                $index === $reachedIndex && $step === 'applied' && ! $closedBadly => 'done',
                $index === $reachedIndex => $closedBadly ? 'failed' : 'current',
                $index === $reachedIndex + 1 && $reached === 'applied' && ! $closedBadly => 'current',
                default => 'upcoming',
            };
        }

        return new self($state, $headline, $message, $steps, $nextInterview, $pendingOffer);
    }
}
