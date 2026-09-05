<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Enums\ApplicantSource;
use App\Enums\ApplicationStatus;
use App\Enums\InterviewStatus;
use App\Enums\OfferStatus;
use App\Enums\VacancyStatus;
use App\Events\ApplicationReceived;
use App\Events\ApplicationRejected;
use App\Events\ApplicationStageChanged;
use App\Events\InterviewScheduled;
use App\Events\OfferSent;
use App\Exceptions\RecruitmentException;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Interview;
use App\Models\Offer;
use App\Models\User;
use App\Models\Vacancy;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecruitmentService
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Register (or reuse) an applicant and attach an application to the vacancy
     * at its first pipeline stage.
     *
     * @param  array{first_name: string, last_name: string, email: string, phone?: string|null, location?: string|null, linkedin_url?: string|null, resume_disk?: string|null, resume_path?: string|null}  $applicant
     * @param  array{cover_letter?: string|null, expected_salary_cents?: int|null, source?: ApplicantSource|string|null, referred_by_employee_id?: int|null}  $application
     */
    public function apply(Vacancy $vacancy, array $applicant, array $application = [], ?User $actor = null): Application
    {
        if (! $vacancy->status->isAcceptingApplications()) {
            throw RecruitmentException::vacancyNotOpen();
        }

        $source = $this->normaliseSource($application['source'] ?? null);

        return $this->context->run($vacancy->tenant, function () use ($vacancy, $applicant, $application, $source, $actor): Application {
            return DB::transaction(function () use ($vacancy, $applicant, $application, $source, $actor): Application {
                $candidate = Applicant::query()->firstOrCreate(
                    ['tenant_id' => $vacancy->tenant_id, 'email' => mb_strtolower(trim($applicant['email']))],
                    [
                        'first_name' => $applicant['first_name'],
                        'last_name' => $applicant['last_name'],
                        'phone' => $applicant['phone'] ?? null,
                        'location' => $applicant['location'] ?? null,
                        'linkedin_url' => $applicant['linkedin_url'] ?? null,
                        'resume_disk' => $applicant['resume_disk'] ?? null,
                        'resume_path' => $applicant['resume_path'] ?? null,
                        'source' => $source,
                        'referred_by_employee_id' => $application['referred_by_employee_id'] ?? null,
                    ],
                );

                if ($candidate->wasRecentlyCreated === false) {
                    // keep the freshest CV / phone we were given
                    $candidate->fill(array_filter([
                        'phone' => $applicant['phone'] ?? null,
                        'resume_disk' => $applicant['resume_disk'] ?? null,
                        'resume_path' => $applicant['resume_path'] ?? null,
                    ]))->save();
                }

                if (Application::query()->where('vacancy_id', $vacancy->getKey())->where('applicant_id', $candidate->getKey())->exists()) {
                    throw RecruitmentException::duplicateApplication();
                }

                $record = Application::query()->create([
                    'vacancy_id' => $vacancy->getKey(),
                    'applicant_id' => $candidate->getKey(),
                    'stage' => $vacancy->firstStage(),
                    'status' => ApplicationStatus::Active,
                    'cover_letter' => $application['cover_letter'] ?? null,
                    'expected_salary_cents' => $application['expected_salary_cents'] ?? null,
                    'source' => $source,
                    'applied_at' => CarbonImmutable::now(),
                    'stage_changed_at' => CarbonImmutable::now(),
                ]);

                $this->log($record, 'stage_change', $actor, toStage: $record->stage, note: 'Application received');

                ApplicationReceived::dispatch($record);

                return $record;
            });
        });
    }

    public function moveStage(Application $application, string $toStage, ?User $actor = null): Application
    {
        $vacancy = $application->vacancy;

        if (! in_array($toStage, $vacancy->stages(), true)) {
            throw RecruitmentException::unknownStage($toStage);
        }

        if (! $application->status->isOpen()) {
            throw RecruitmentException::applicationClosed();
        }

        $from = $application->stage;

        if ($from === $toStage) {
            return $application;
        }

        return DB::transaction(function () use ($application, $vacancy, $from, $toStage, $actor): Application {
            $attributes = ['stage' => $toStage, 'stage_changed_at' => CarbonImmutable::now()];

            if ($toStage === $vacancy->finalStage()) {
                $attributes['status'] = ApplicationStatus::Hired;
                $attributes['decided_at'] = CarbonImmutable::now();
                $this->fillVacancySeat($vacancy);
            }

            $application->forceFill($attributes)->save();
            $this->log($application, 'stage_change', $actor, fromStage: $from, toStage: $toStage);

            ApplicationStageChanged::dispatch($application, $from, $toStage);

            return $application;
        });
    }

    public function reject(Application $application, ?string $reason, ?User $actor = null): Application
    {
        if (! $application->status->isOpen()) {
            throw RecruitmentException::applicationClosed();
        }

        return DB::transaction(function () use ($application, $reason, $actor): Application {
            $application->forceFill([
                'status' => ApplicationStatus::Rejected,
                'rejected_reason' => $reason,
                'decided_at' => CarbonImmutable::now(),
            ])->save();

            $this->log($application, 'rejected', $actor, note: $reason);
            ApplicationRejected::dispatch($application);

            return $application;
        });
    }

    public function withdraw(Application $application, ?User $actor = null): Application
    {
        if (! $application->status->isOpen()) {
            throw RecruitmentException::applicationClosed();
        }

        $application->forceFill([
            'status' => ApplicationStatus::Withdrawn,
            'decided_at' => CarbonImmutable::now(),
        ])->save();

        $this->log($application, 'note', $actor, note: 'Candidate withdrew');

        return $application;
    }

    public function rate(Application $application, ?int $rating, ?User $actor = null): Application
    {
        $application->forceFill(['rating' => $rating])->save();
        $this->log($application, 'rating', $actor, note: $rating === null ? null : "Rated {$rating}/5");

        return $application;
    }

    /**
     * @param  array{round?: int, mode?: string, interviewer_id?: int|null, scheduled_at: string|CarbonImmutable, duration_minutes?: int, location?: string|null, meeting_url?: string|null}  $data
     */
    public function scheduleInterview(Application $application, array $data, ?User $actor = null): Interview
    {
        if (! $application->status->isOpen()) {
            throw RecruitmentException::applicationClosed();
        }

        return DB::transaction(function () use ($application, $data, $actor): Interview {
            $round = $data['round'] ?? ((int) $application->interviews()->max('round') + 1);

            $interview = $application->interviews()->create([
                'tenant_id' => $application->tenant_id,
                'round' => $round,
                'mode' => $data['mode'] ?? 'video',
                'interviewer_id' => $data['interviewer_id'] ?? null,
                'scheduled_at' => $data['scheduled_at'] instanceof CarbonImmutable
                    ? $data['scheduled_at']
                    : CarbonImmutable::parse($data['scheduled_at']),
                'duration_minutes' => $data['duration_minutes'] ?? 45,
                'location' => $data['location'] ?? null,
                'meeting_url' => $data['meeting_url'] ?? null,
                'status' => InterviewStatus::Scheduled,
                'scheduled_by' => $actor?->getKey(),
            ]);

            $this->log($application, 'interview', $actor, note: "Interview round {$round} scheduled");
            InterviewScheduled::dispatch($interview);

            return $interview;
        });
    }

    /**
     * @param  array{status?: string, recommendation?: string|null, rating?: int|null, feedback?: string|null}  $data
     */
    public function recordInterviewFeedback(Interview $interview, array $data): Interview
    {
        if ($interview->status === InterviewStatus::Cancelled) {
            throw RecruitmentException::interviewNotScheduled();
        }

        $interview->forceFill(array_filter([
            'status' => $data['status'] ?? InterviewStatus::Completed->value,
            'recommendation' => $data['recommendation'] ?? null,
            'rating' => $data['rating'] ?? null,
            'feedback' => $data['feedback'] ?? null,
            'feedback_submitted_at' => CarbonImmutable::now(),
        ], static fn ($v): bool => $v !== null))->save();

        return $interview;
    }

    /**
     * @param  array{job_title?: string|null, department_id?: int|null, designation_id?: int|null, employment_type_id?: int|null, salary_cents: int, salary_currency?: string, start_date?: string|null, expires_on?: string|null, notes?: string|null}  $data
     */
    public function makeOffer(Application $application, array $data, User $actor): Offer
    {
        if (! $application->status->isOpen()) {
            throw RecruitmentException::applicationClosed();
        }

        return DB::transaction(function () use ($application, $data, $actor): Offer {
            $offer = $application->offers()->create([
                'tenant_id' => $application->tenant_id,
                'job_title' => $data['job_title'] ?? $application->vacancy->title,
                'department_id' => $data['department_id'] ?? $application->vacancy->department_id,
                'designation_id' => $data['designation_id'] ?? $application->vacancy->designation_id,
                'employment_type_id' => $data['employment_type_id'] ?? $application->vacancy->employment_type_id,
                'salary_cents' => $data['salary_cents'],
                'salary_currency' => $data['salary_currency'] ?? ($application->vacancy->salary_currency ?? 'KES'),
                'start_date' => $data['start_date'] ?? null,
                'expires_on' => $data['expires_on'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => OfferStatus::Draft,
                'created_by' => $actor->getKey(),
            ]);

            if (in_array('offer', $application->vacancy->stages(), true) && $application->stage !== 'offer') {
                $this->moveStage($application, 'offer', $actor);
            }

            $this->log($application, 'offer', $actor, note: 'Offer drafted');

            return $offer;
        });
    }

    public function sendOffer(Offer $offer, ?User $approver = null): Offer
    {
        if ($offer->status !== OfferStatus::Draft) {
            throw RecruitmentException::offerNotPending();
        }

        $offer->forceFill([
            'status' => OfferStatus::Sent,
            'approved_by' => $approver?->getKey(),
            'sent_at' => CarbonImmutable::now(),
        ])->save();

        OfferSent::dispatch($offer);

        return $offer;
    }

    public function respondToOffer(Offer $offer, bool $accepted, ?string $note = null, ?User $actor = null): Offer
    {
        if (! $offer->status->isPending()) {
            throw RecruitmentException::offerNotPending();
        }

        return DB::transaction(function () use ($offer, $accepted, $note, $actor): Offer {
            $offer->forceFill([
                'status' => $accepted ? OfferStatus::Accepted : OfferStatus::Declined,
                'responded_at' => CarbonImmutable::now(),
                'notes' => $note ?? $offer->notes,
            ])->save();

            $application = $offer->application;

            if ($accepted) {
                $this->moveStage($application, $application->vacancy->finalStage(), $actor);
            } else {
                $this->log($application, 'offer', $actor, note: 'Offer declined');
            }

            return $offer;
        });
    }

    public function rescindOffer(Offer $offer): Offer
    {
        if (! $offer->status->isPending()) {
            throw RecruitmentException::offerNotPending();
        }

        $offer->forceFill(['status' => OfferStatus::Rescinded])->save();

        return $offer;
    }

    private function fillVacancySeat(Vacancy $vacancy): void
    {
        $vacancy->forceFill(['positions_filled' => $vacancy->positions_filled + 1])->save();

        if ($vacancy->remainingHeadcount() <= 0 && ! $vacancy->status->isTerminal()) {
            $vacancy->forceFill(['status' => VacancyStatus::Filled])->save();
        }
    }

    private function normaliseSource(ApplicantSource|string|null $source): ApplicantSource
    {
        if ($source instanceof ApplicantSource) {
            return $source;
        }

        return $source === null ? ApplicantSource::CareersSite : (ApplicantSource::tryFrom($source) ?? ApplicantSource::Other);
    }

    private function log(
        Application $application,
        string $type,
        ?User $actor,
        ?string $fromStage = null,
        ?string $toStage = null,
        ?string $note = null,
    ): void {
        $application->activities()->create([
            'tenant_id' => $application->tenant_id,
            'type' => $type,
            'from_stage' => $fromStage,
            'to_stage' => $toStage,
            'note' => $note,
            'performed_by' => $actor?->getKey(),
        ]);
    }
}
