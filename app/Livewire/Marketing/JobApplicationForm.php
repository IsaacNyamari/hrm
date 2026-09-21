<?php

declare(strict_types=1);

namespace App\Livewire\Marketing;

use App\Enums\ApplicantSource;
use App\Exceptions\RecruitmentException;
use App\Services\Recruitment\RecruitmentService;
use App\Support\Recruitment\PublicJobs;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Public application form on a job's page. No account is needed: on success
 * the candidate gets a tracking code (also emailed) for the tracking page.
 */
class JobApplicationForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $organization = '';

    #[Locked]
    public string $vacancy = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public string $location = '';

    public string $linkedin_url = '';

    public string $cover_letter = '';

    /** @var UploadedFile|null */
    public $resume = null;

    /** Honeypot: humans never see or fill this. */
    public string $website = '';

    public ?string $trackingCode = null;

    public function mount(string $organization, string $vacancy): void
    {
        $this->organization = $organization;
        $this->vacancy = $vacancy;
    }

    public function submit(RecruitmentService $service, PublicJobs $jobs): void
    {
        $this->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:150'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'cover_letter' => ['nullable', 'string', 'max:20000'],
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:'.(int) config('recruitment.resume_max_kb', 10240)],
        ], [], ['resume' => __('CV')]);

        if ($this->website !== '') {
            $this->trackingCode = 'APP-00000000'; // silently drop bots

            return;
        }

        $key = 'job-apply:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 6)) {
            $this->addError('email', __('Too many applications from your connection. Please try again later.'));

            return;
        }

        $vacancy = $jobs->find($this->organization, $this->vacancy);

        if ($vacancy === null) {
            $this->addError('email', __('This job is no longer accepting applications.'));

            return;
        }

        RateLimiter::hit($key, 600);

        $disk = (string) config('recruitment.resume_disk', 'local');
        $path = $this->resume->store("tenants/{$vacancy->tenant->uuid}/careers", $disk);

        try {
            $application = $service->apply($vacancy, [
                'first_name' => trim($this->first_name),
                'last_name' => trim($this->last_name),
                'email' => $this->email,
                'phone' => trim($this->phone),
                'location' => $this->location ?: null,
                'linkedin_url' => $this->linkedin_url ?: null,
                'resume_disk' => $disk,
                'resume_path' => $path === false ? null : $path,
            ], [
                'cover_letter' => $this->cover_letter ?: null,
                'source' => ApplicantSource::CareersSite->value,
            ]);
        } catch (RecruitmentException $e) {
            $this->addError('email', $e->getMessage());

            return;
        }

        $this->trackingCode = $application->tracking_code;
        $this->reset('first_name', 'last_name', 'phone', 'location', 'linkedin_url', 'cover_letter', 'resume');
    }

    public function render(): View
    {
        return view('livewire.marketing.job-application-form');
    }
}
