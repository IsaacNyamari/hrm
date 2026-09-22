<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile\Public;

use App\Enums\ApplicantSource;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\PublicJobResource;
use App\Livewire\Marketing\JobApplicationForm;
use App\Models\Tenant;
use App\Models\Vacancy;
use App\Services\Recruitment\RecruitmentService;
use App\Support\Recruitment\PublicJobs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The public job board (open vacancies across organisations) and applying
 * without an account. Same listing rules, validation and rate limits as the
 * website ({@see JobApplicationForm}); the candidate gets a tracking code.
 */
class JobController extends Controller
{
    public function index(Request $request, PublicJobs $jobs): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'organization' => ['nullable', 'string', 'max:100'],
            'remote' => ['nullable', 'boolean'],
        ]);

        $list = $jobs->list(
            (string) ($filters['search'] ?? ''),
            $filters['organization'] ?? null,
            (bool) ($filters['remote'] ?? false),
        );

        return response()->json([
            'data' => $list->map(fn (Vacancy $job): array => (new PublicJobResource($job))->resolve($request))->all(),
            'meta' => [
                'organizations' => $jobs->organizations()
                    ->map(fn (Tenant $tenant): array => ['name' => $tenant->name, 'slug' => $tenant->slug])
                    ->all(),
            ],
        ]);
    }

    public function show(Request $request, PublicJobs $jobs, string $organization, string $vacancy): JsonResponse
    {
        $job = $jobs->find($organization, $vacancy);
        abort_if($job === null, 404, 'This job is no longer open.');

        return response()->json(['data' => (new PublicJobResource($job, detailed: true))->resolve($request)]);
    }

    public function apply(Request $request, RecruitmentService $service, PublicJobs $jobs, string $organization, string $vacancy): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:150'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'cover_letter' => ['nullable', 'string', 'max:20000'],
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:'.(int) config('recruitment.resume_max_kb', 10240)],
            'website' => ['nullable', 'string', 'max:255'],
        ], [], ['resume' => __('CV')]);

        // Honeypot: humans never see or fill this field. Answer as if it worked.
        if (($data['website'] ?? '') !== '') {
            return response()->json(['data' => ['tracking_code' => 'APP-00000000', 'email' => $data['email']]], 201);
        }

        // Shares the website's per-connection budget.
        $key = 'job-apply:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw ValidationException::withMessages([
                'email' => __('Too many applications from your connection. Please try again later.'),
            ])->status(429);
        }

        $job = $jobs->find($organization, $vacancy);

        if ($job === null) {
            throw ValidationException::withMessages(['email' => __('This job is no longer accepting applications.')]);
        }

        RateLimiter::hit($key, 600);

        $disk = (string) config('recruitment.resume_disk', 'local');
        $path = $request->file('resume')?->store("tenants/{$job->tenant->uuid}/careers", $disk);

        $application = $service->apply($job, [
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => $data['email'],
            'phone' => trim($data['phone']),
            'location' => $data['location'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'resume_disk' => $disk,
            'resume_path' => is_string($path) ? $path : null,
        ], [
            'cover_letter' => $data['cover_letter'] ?? null,
            'source' => ApplicantSource::CareersSite->value,
        ]);

        return response()->json(['data' => [
            'tracking_code' => $application->tracking_code,
            'email' => mb_strtolower(trim($data['email'])),
        ]], 201);
    }
}
