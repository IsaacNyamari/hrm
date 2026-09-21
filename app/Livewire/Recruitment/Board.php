<?php

declare(strict_types=1);

namespace App\Livewire\Recruitment;

use App\Enums\VacancyStatus;
use App\Exceptions\RecruitmentException;
use App\Facades\Tenancy;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\Offer;
use App\Models\Vacancy;
use App\Services\Recruitment\RecruitmentService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

class Board extends Component
{
    use BindsTenant;

    public ?string $vacancyUuid = null;

    // Vacancy form
    public bool $showVacancyForm = false;

    public ?string $editingVacancyUuid = null;

    /** @var array<string, mixed> */
    public array $vacancyForm = [];

    // Add candidate
    public bool $showCandidateForm = false;

    /** @var array<string, mixed> */
    public array $candidateForm = [];

    // Interview scheduling
    public string $schedulingAppUuid = '';

    /** @var array<string, mixed> */
    public array $interviewForm = [];

    // Interview feedback
    public string $feedbackInterviewUuid = '';

    /** @var array<string, mixed> */
    public array $feedbackForm = [];

    // Offer
    public string $offeringAppUuid = '';

    /** @var array<string, mixed> */
    public array $offerForm = [];

    public function mount(): void
    {
        $this->vacancyUuid ??= Vacancy::query()->where('status', 'open')->value('uuid')
            ?? Vacancy::query()->value('uuid');
    }

    public function moveStage(string $applicationUuid, string $toStage, RecruitmentService $service): void
    {
        $application = Application::query()->where('uuid', $applicationUuid)->first();

        if ($application === null || Gate::denies('manage', $application)) {
            Flux::toast(variant: 'danger', text: __('You cannot move that candidate.'));

            return;
        }

        if (! $application->status->isOpen()) {
            Flux::toast(variant: 'danger', text: __('This application is no longer active.'));

            return;
        }

        try {
            $service->moveStage($application, $toStage, auth()->user());
            Flux::toast(variant: 'success', text: __('Candidate moved to :stage.', ['stage' => Str::headline($toStage)]));
        } catch (RecruitmentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function rejectApplication(string $applicationUuid, RecruitmentService $service): void
    {
        $application = Application::query()->where('uuid', $applicationUuid)->first();

        if ($application === null || Gate::denies('manage', $application)) {
            Flux::toast(variant: 'danger', text: __('You cannot reject that candidate.'));

            return;
        }

        try {
            $service->reject($application, null, auth()->user());
            Flux::modal('reject-app-'.$applicationUuid)->close();
            Flux::toast(variant: 'success', text: __('Candidate rejected.'));
        } catch (RecruitmentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    // --- Vacancies -------------------------------------------------------

    public function startCreateVacancy(): void
    {
        Gate::authorize('create', Vacancy::class);
        $this->resetErrorBag();
        $this->editingVacancyUuid = null;
        $this->vacancyForm = $this->blankVacancyForm();
        $this->showVacancyForm = true;
    }

    public function startEditVacancy(string $uuid): void
    {
        $vacancy = Vacancy::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $vacancy);

        $this->resetErrorBag();
        $this->editingVacancyUuid = $vacancy->uuid;
        $this->vacancyForm = [
            'title' => $vacancy->title,
            'department_id' => (string) $vacancy->department_id,
            'branch_id' => (string) $vacancy->branch_id,
            'headcount' => (string) $vacancy->headcount,
            'location' => (string) $vacancy->location,
            'is_remote' => $vacancy->is_remote,
            'closes_on' => (string) $vacancy->closes_on?->toDateString(),
        ];
        $this->showVacancyForm = true;
    }

    public function saveVacancy(): void
    {
        $data = $this->validate([
            'vacancyForm.title' => ['required', 'string', 'max:150'],
            'vacancyForm.department_id' => ['nullable', 'integer'],
            'vacancyForm.branch_id' => ['nullable', 'integer'],
            'vacancyForm.headcount' => ['required', 'integer', 'between:1,999'],
            'vacancyForm.location' => ['nullable', 'string', 'max:150'],
            'vacancyForm.is_remote' => ['boolean'],
            'vacancyForm.closes_on' => ['nullable', 'date'],
        ])['vacancyForm'];

        $attributes = [
            'title' => $data['title'],
            'department_id' => $data['department_id'] !== '' ? (int) $data['department_id'] : null,
            'branch_id' => $data['branch_id'] !== '' ? (int) $data['branch_id'] : null,
            'headcount' => (int) $data['headcount'],
            'location' => $data['location'] !== '' ? $data['location'] : null,
            'is_remote' => (bool) ($data['is_remote'] ?? false),
            'closes_on' => $data['closes_on'] !== '' ? $data['closes_on'] : null,
        ];

        if ($this->editingVacancyUuid === null) {
            Gate::authorize('create', Vacancy::class);
            $vacancy = Vacancy::query()->create($attributes + [
                'slug' => $this->uniqueSlug($data['title']),
                'opened_by' => auth()->id(),
                'status' => VacancyStatus::Draft,
                'salary_currency' => Tenancy::getOrFail()->currency,
                'pipeline_stages' => config('recruitment.default_pipeline', ['applied', 'screening', 'interview', 'offer', 'hired']),
            ]);
            $this->vacancyUuid = $vacancy->uuid;
            Flux::toast(variant: 'success', text: __('Vacancy created.'));
        } else {
            $vacancy = Vacancy::query()->where('uuid', $this->editingVacancyUuid)->firstOrFail();
            Gate::authorize('update', $vacancy);
            $vacancy->update($attributes);
            Flux::toast(variant: 'success', text: __('Vacancy updated.'));
        }

        $this->vacancyForm = $this->blankVacancyForm();
        $this->editingVacancyUuid = null;
        $this->showVacancyForm = false;
    }

    public function publishVacancy(string $uuid): void
    {
        $vacancy = Vacancy::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $vacancy);

        if ($vacancy->status->isTerminal()) {
            Flux::toast(variant: 'danger', text: __('This vacancy is closed.'));

            return;
        }

        $vacancy->forceFill(['status' => VacancyStatus::Open, 'published_at' => $vacancy->published_at ?? now()])->save();
        Flux::toast(variant: 'success', text: __('Vacancy published.'));
    }

    public function closeVacancy(string $uuid): void
    {
        $vacancy = Vacancy::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $vacancy);

        $vacancy->forceFill(['status' => VacancyStatus::Closed])->save();
        Flux::modal('close-vacancy-'.$uuid)->close();
        Flux::toast(variant: 'success', text: __('Vacancy closed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function blankVacancyForm(): array
    {
        return ['title' => '', 'department_id' => '', 'branch_id' => '', 'headcount' => '1', 'location' => '', 'is_remote' => false, 'closes_on' => ''];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $n = 2;

        while (Vacancy::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    // --- Candidates --------------------------------------------------------

    public function startAddCandidate(): void
    {
        abort_unless(auth()->user()?->can('recruitment.manage'), 403);
        $this->resetErrorBag();
        $this->candidateForm = ['first_name' => '', 'last_name' => '', 'email' => ''];
        $this->showCandidateForm = true;
    }

    public function addCandidate(RecruitmentService $service): void
    {
        abort_unless(auth()->user()?->can('recruitment.manage'), 403);

        $data = $this->validate([
            'candidateForm.first_name' => ['required', 'string', 'max:100'],
            'candidateForm.last_name' => ['required', 'string', 'max:100'],
            'candidateForm.email' => ['required', 'email'],
        ])['candidateForm'];

        $vacancy = Vacancy::query()->where('uuid', $this->vacancyUuid)->first();

        if ($vacancy === null) {
            Flux::toast(variant: 'danger', text: __('Select a vacancy first.'));

            return;
        }

        try {
            $service->apply($vacancy, $data, [], auth()->user());
            $this->showCandidateForm = false;
            Flux::toast(variant: 'success', text: __('Candidate added.'));
        } catch (RecruitmentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    // --- Interviews --------------------------------------------------------

    public function startScheduleInterview(string $applicationUuid): void
    {
        $application = Application::query()->where('uuid', $applicationUuid)->firstOrFail();
        Gate::authorize('manage', $application);

        $this->schedulingAppUuid = $applicationUuid;
        $this->interviewForm = ['scheduled_at' => '', 'interviewer_id' => '', 'mode' => 'video', 'duration_minutes' => '45'];
        Flux::modal('schedule-interview')->show();
    }

    public function scheduleInterview(RecruitmentService $service): void
    {
        $application = Application::query()->where('uuid', $this->schedulingAppUuid)->first();

        if ($application === null || Gate::denies('manage', $application)) {
            Flux::toast(variant: 'danger', text: __('You cannot schedule that interview.'));

            return;
        }

        $data = $this->validate([
            'interviewForm.scheduled_at' => ['required', 'date'],
            'interviewForm.interviewer_id' => ['nullable', 'integer'],
            'interviewForm.mode' => ['required', 'string'],
            'interviewForm.duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
        ])['interviewForm'];

        $interviewerId = null;

        if ($data['interviewer_id'] !== '') {
            $interviewer = Employee::query()->find((int) $data['interviewer_id']);
            $interviewerId = $interviewer?->user_id;
        }

        try {
            $service->scheduleInterview($application, [
                'scheduled_at' => $data['scheduled_at'],
                'interviewer_id' => $interviewerId,
                'mode' => $data['mode'],
                'duration_minutes' => (int) $data['duration_minutes'],
            ], auth()->user());

            $this->schedulingAppUuid = '';
            Flux::modal('schedule-interview')->close();
            Flux::toast(variant: 'success', text: __('Interview scheduled.'));
        } catch (RecruitmentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function startFeedback(string $interviewUuid): void
    {
        $interview = Interview::query()->where('uuid', $interviewUuid)->firstOrFail();
        Gate::authorize('submitFeedback', $interview);

        $this->feedbackInterviewUuid = $interviewUuid;
        $this->feedbackForm = ['recommendation' => 'yes', 'rating' => '3', 'feedback' => ''];
        Flux::modal('interview-feedback')->show();
    }

    public function saveFeedback(RecruitmentService $service): void
    {
        $interview = Interview::query()->where('uuid', $this->feedbackInterviewUuid)->first();

        if ($interview === null || Gate::denies('submitFeedback', $interview)) {
            Flux::toast(variant: 'danger', text: __('You cannot record feedback for that interview.'));

            return;
        }

        $data = $this->validate([
            'feedbackForm.recommendation' => ['required', 'string'],
            'feedbackForm.rating' => ['required', 'integer', 'between:1,5'],
            'feedbackForm.feedback' => ['nullable', 'string', 'max:5000'],
        ])['feedbackForm'];

        try {
            $service->recordInterviewFeedback($interview, [
                'recommendation' => $data['recommendation'],
                'rating' => (int) $data['rating'],
                'feedback' => $data['feedback'] !== '' ? $data['feedback'] : null,
            ]);

            $this->feedbackInterviewUuid = '';
            Flux::modal('interview-feedback')->close();
            Flux::toast(variant: 'success', text: __('Feedback recorded.'));
        } catch (RecruitmentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    // --- Offers --------------------------------------------------------

    public function startMakeOffer(string $applicationUuid): void
    {
        $application = Application::query()->where('uuid', $applicationUuid)->firstOrFail();
        Gate::authorize('manage', $application);

        $this->offeringAppUuid = $applicationUuid;
        $this->offerForm = ['salary_cents' => '', 'start_date' => ''];
        Flux::modal('make-offer')->show();
    }

    public function makeOffer(RecruitmentService $service): void
    {
        $application = Application::query()->where('uuid', $this->offeringAppUuid)->first();

        if ($application === null || Gate::denies('manage', $application)) {
            Flux::toast(variant: 'danger', text: __('You cannot make an offer for that candidate.'));

            return;
        }

        $data = $this->validate([
            'offerForm.salary_cents' => ['required', 'integer', 'min:0'],
            'offerForm.start_date' => ['nullable', 'date'],
        ])['offerForm'];

        try {
            $service->makeOffer($application, [
                'salary_cents' => (int) $data['salary_cents'],
                'start_date' => $data['start_date'] !== '' ? $data['start_date'] : null,
            ], auth()->user());

            $this->offeringAppUuid = '';
            Flux::modal('make-offer')->close();
            Flux::toast(variant: 'success', text: __('Offer drafted.'));
        } catch (RecruitmentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function sendOffer(string $offerUuid, RecruitmentService $service): void
    {
        $offer = Offer::query()->where('uuid', $offerUuid)->first();

        if ($offer === null || Gate::denies('manage', $offer)) {
            Flux::toast(variant: 'danger', text: __('You cannot send that offer.'));

            return;
        }

        try {
            $service->sendOffer($offer, auth()->user());
            Flux::toast(variant: 'success', text: __('Offer sent.'));
        } catch (RecruitmentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function render(): View
    {
        $user = auth()->user();

        $vacancies = Vacancy::query()->orderByDesc('id')->get(['uuid', 'title', 'status']);
        $vacancy = $this->vacancyUuid !== null
            ? Vacancy::query()->where('uuid', $this->vacancyUuid)->first()
            : null;

        $columns = [];
        if ($vacancy !== null) {
            $applications = $vacancy->applications()
                ->with('applicant:id,first_name,last_name')
                ->orderByDesc('id')
                ->get();

            foreach ($vacancy->stages() as $stage) {
                $columns[$stage] = $applications->where('stage', $stage)->values();
            }
        }

        $canManage = $user?->can('recruitment.manage');

        $myInterviews = $user !== null
            ? Interview::query()
                ->where('interviewer_id', $user->getKey())
                ->where('status', 'scheduled')
                ->with(['application.applicant:id,first_name,last_name'])
                ->orderBy('scheduled_at')
                ->get()
            : collect();

        $offersToSend = $canManage
            ? Offer::query()->where('status', 'draft')->with(['application.applicant:id,first_name,last_name'])->latest('id')->get()
            : collect();

        return view('livewire.recruitment.board', [
            'vacancies' => $vacancies,
            'vacancy' => $vacancy,
            'columns' => $columns,
            'canManage' => $canManage,
            'departments' => $canManage ? Department::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'branches' => $canManage ? Branch::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'employees' => $canManage ? Employee::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name']) : collect(),
            'myInterviews' => $myInterviews,
            'offersToSend' => $offersToSend,
        ]);
    }
}
