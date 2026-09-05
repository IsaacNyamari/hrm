<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApplicantSource;
use App\Enums\VacancyStatus;
use App\Facades\Tenancy;
use App\Http\Middleware\ResolveTenant;
use App\Models\Vacancy;
use App\Services\Recruitment\RecruitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public per-tenant careers board. The tenant is resolved from the request host
 * by {@see ResolveTenant}; nothing here trusts a tenant id
 * from the client. Only Open vacancies are ever exposed.
 */
class CareersController extends Controller
{
    public function __construct(private readonly RecruitmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $vacancies = Vacancy::query()
            ->where('status', VacancyStatus::Open->value)
            ->with('department:id,name')
            ->orderByDesc('published_at')
            ->get();

        return response()->json(['data' => $vacancies->map(fn (Vacancy $v): array => [
            'slug' => $v->slug,
            'title' => $v->title,
            'department' => $v->department?->name,
            'location' => $v->location,
            'is_remote' => $v->is_remote,
        ])]);
    }

    public function show(Vacancy $vacancy): JsonResponse
    {
        abort_unless($vacancy->status === VacancyStatus::Open, 404);

        return response()->json(['data' => [
            'slug' => $vacancy->slug,
            'title' => $vacancy->title,
            'department' => $vacancy->department?->name,
            'location' => $vacancy->location,
            'is_remote' => $vacancy->is_remote,
            'description' => $vacancy->description,
            'requirements' => $vacancy->requirements,
        ]]);
    }

    public function apply(Request $request, Vacancy $vacancy): JsonResponse
    {
        abort_unless($vacancy->status === VacancyStatus::Open, 404);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:150'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'cover_letter' => ['nullable', 'string', 'max:20000'],
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:'.(int) config('recruitment.resume_max_kb', 10240)],
        ]);

        $applicant = [
            'first_name' => (string) $data['first_name'],
            'last_name' => (string) $data['last_name'],
            'email' => (string) $data['email'],
            'phone' => isset($data['phone']) ? (string) $data['phone'] : null,
            'location' => isset($data['location']) ? (string) $data['location'] : null,
            'linkedin_url' => isset($data['linkedin_url']) ? (string) $data['linkedin_url'] : null,
        ];

        if ($request->hasFile('resume')) {
            $tenant = Tenancy::getOrFail();
            $disk = (string) config('recruitment.resume_disk', 'local');
            $path = $request->file('resume')->store("tenants/{$tenant->uuid}/careers", $disk);
            $applicant['resume_disk'] = $disk;
            $applicant['resume_path'] = $path === false ? null : $path;
        }

        $application = $this->service->apply($vacancy, $applicant, [
            'cover_letter' => isset($data['cover_letter']) ? (string) $data['cover_letter'] : null,
            'source' => ApplicantSource::CareersSite->value,
        ]);

        return response()->json([
            'data' => ['reference' => $application->uuid],
            'message' => 'Thanks for applying. We will be in touch.',
        ], 201);
    }
}
